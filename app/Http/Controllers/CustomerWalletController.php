<?php

namespace App\Http\Controllers;

use App\Exports\CustomerWalletMutasiExport;
use App\Http\Requests\CustomerWalletEntryRequest;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletEntry;
use App\Models\User;
use App\Services\CustomerWalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Saldo Customer — HANYA superadmin.
 * Saldo hanya catatan mutasi (top up / pengurangan manual). Tidak dipakai kasir dan
 * tidak menyentuh penjualan, stok, kas, maupun laporan penjualan.
 */
class CustomerWalletController extends Controller
{
    public function __construct()
    {
        // Lapis kedua selain middleware di routes/web.php.
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->check() && auth()->user()->isSuperadmin(), 403, 'Menu ini khusus superadmin.');

            return $next($request);
        });
    }

    /** Daftar customer beserta saldo terkininya (Customer Umum tidak punya saldo). */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('saldo') === 'ada' ? 'ada' : 'semua';

        $customers = User::query()
            ->where('role', 'customer')
            ->where(function ($w) {
                $w->whereNull('username')->orWhere('username', '<>', User::DEFAULT_CUSTOMER_USERNAME);
            })
            ->with('wallet')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('no_telp', 'like', "%{$q}%")
                        ->orWhere('alamat', 'like', "%{$q}%");
                });
            })
            ->when($filter === 'ada', fn ($query) => $query->whereHas('wallet', fn ($w) => $w->where('balance', '>', 0)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('saldo-customer.index', [
            'autoOpen' => $this->resolveAutoOpen($request),
            'customers' => $customers,
            'q' => $q,
            'filter' => $filter,
            'totalBalance' => (float) CustomerWallet::sum('balance'),
            'walletCount' => CustomerWallet::where('balance', '>', 0)->count(),
        ]);
    }

    /**
     * Form sekarang berupa popup di halaman daftar. Alamat lama (/saldo-customer/{id}/topup
     * dan /kurangi) tetap hidup dan langsung membuka popup-nya.
     */
    /** Mutasi satu customer, seperti mutasi rekening. */
    public function mutasi(Request $request, User $customer, CustomerWalletService $wallet)
    {
        $this->ensureEligible($customer);
        [$mulai, $selesai] = $this->period($request);

        return view('saldo-customer.mutasi', [
            'customer' => $customer,
            'stmt' => $wallet->statement($customer, $mulai, $selesai),
            'mulai' => $mulai,
            'selesai' => $selesai,
            'saldoTerkini' => $wallet->balanceOf($customer),
        ]);
    }

    public function mutasiPrint(Request $request, User $customer, CustomerWalletService $wallet)
    {
        $this->ensureEligible($customer);
        [$mulai, $selesai] = $this->period($request);

        return view('saldo-customer.print', [
            'customer' => $customer,
            'stmt' => $wallet->statement($customer, $mulai, $selesai),
            'identity' => \App\Support\ReportIdentity::get(),
        ]);
    }

    public function mutasiExport(Request $request, User $customer, CustomerWalletService $wallet)
    {
        $this->ensureEligible($customer);
        [$mulai, $selesai] = $this->period($request);

        $export = new CustomerWalletMutasiExport($request, $customer, $wallet->statement($customer, $mulai, $selesai));

        return Excel::download($export, $export->downloadName());
    }

    /**
     * Periode mutasi dari query string. Tanpa parameter sama sekali = bulan ini.
     * Parameter ada tapi kosong = tanpa batas (misal "Semua").
     *
     * @return array{0:?string,1:?string}
     */
    private function period(Request $request): array
    {
        if (! $request->has('tanggal_mulai') && ! $request->has('tanggal_selesai')) {
            return [now()->startOfMonth()->toDateString(), now()->toDateString()];
        }

        $clean = function ($v) {
            try {
                return $v ? Carbon::parse($v)->toDateString() : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        $mulai = $clean($request->query('tanggal_mulai'));
        $selesai = $clean($request->query('tanggal_selesai'));

        if ($mulai && $selesai && $mulai > $selesai) {
            [$mulai, $selesai] = [$selesai, $mulai];
        }

        return [$mulai, $selesai];
    }

    public function topupForm(User $customer)
    {
        return $this->openPopup($customer, CustomerWalletEntry::TYPE_TOPUP);
    }

    public function kurangiForm(User $customer)
    {
        return $this->openPopup($customer, CustomerWalletEntry::TYPE_PENGURANGAN);
    }

    public function topupStore(CustomerWalletEntryRequest $request, User $customer, CustomerWalletService $wallet)
    {
        return $this->store($request, $customer, CustomerWalletEntry::TYPE_TOPUP, $wallet);
    }

    public function kurangiStore(CustomerWalletEntryRequest $request, User $customer, CustomerWalletService $wallet)
    {
        return $this->store($request, $customer, CustomerWalletEntry::TYPE_PENGURANGAN, $wallet);
    }

    private function openPopup(User $customer, string $type)
    {
        $this->ensureEligible($customer);

        return redirect()->route('saldo-customer.index', ['buka' => $type.':'.$customer->id]);
    }

    /**
     * Popup yang harus terbuka otomatis saat halaman daftar dimuat:
     * - dari alamat lama (?buka=topup:12), atau
     * - setelah input ditolak validasi (field tersembunyi _open ikut tersimpan di old input).
     */
    private function resolveAutoOpen(Request $request): ?array
    {
        $buka = (string) ($request->query('buka') ?: $request->old('_open', ''));
        [$type, $id] = array_pad(explode(':', $buka, 2), 2, null);

        if (! in_array($type, [CustomerWalletEntry::TYPE_TOPUP, CustomerWalletEntry::TYPE_PENGURANGAN], true)
            || ! ctype_digit((string) $id)) {
            return null;
        }

        $customer = User::find((int) $id);
        if (! $customer || ! $customer->canHaveWallet()) {
            return null;
        }

        return [
            'type' => $type,
            'customer' => $customer,
            'saldo' => (float) ($customer->wallet?->balance ?? 0),
        ];
    }

    private function store(CustomerWalletEntryRequest $request, User $customer, string $type, CustomerWalletService $wallet)
    {
        $this->ensureEligible($customer);

        $data = $request->validated();

        // Token sekali pakai: submit ulang (refresh / klik ganda) tidak mencatat dua kali.
        $lock = 'saldo-customer:submit:'.$data['token'];
        if (! Cache::add($lock, 1, now()->addMinutes(30))) {
            return redirect()->route('saldo-customer.index')
                ->with('toast_error', 'Permintaan ganda diabaikan. Periksa saldo di daftar untuk memastikan.');
        }

        try {
            $entry = $type === CustomerWalletEntry::TYPE_TOPUP
                ? $wallet->topUp($customer, $data['amount'], ($data['note'] ?? ''), $request->user(), $data['tanggal'])
                : $wallet->kurangi($customer, $data['amount'], ($data['note'] ?? ''), $request->user(), $data['tanggal']);
        } catch (RuntimeException $e) {
            Cache::forget($lock); // gagal: form boleh dikirim ulang

            return back()->withInput()->withErrors(['saldo' => $e->getMessage()]);
        }

        $rp = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
        $label = $entry->isTopup() ? 'Top up' : 'Pengurangan';

        // Setelah tersimpan: ke halaman mutasi customer (Tahap D), atau daftar saldo bila belum ada.
        // Periode dimulai dari awal bulan mutasi yang baru dicatat, supaya entri yang
        // tanggalnya diundur ke bulan lalu tetap kelihatan.
        $destination = Route::has('saldo-customer.mutasi')
            ? route('saldo-customer.mutasi', [
                $customer,
                'tanggal_mulai' => $entry->tanggal->copy()->startOfMonth()->toDateString(),
                'tanggal_selesai' => now()->toDateString(),
            ])
            : route('saldo-customer.index');

        return redirect($destination)
            ->with('toast_success', "{$label} {$rp($entry->amount)} untuk {$customer->name} tercatat. Saldo sekarang {$rp($entry->balance_after)}.");
    }

    /** Hanya customer sungguhan (bukan Customer Umum) yang punya saldo. */
    private function ensureEligible(User $customer): void
    {
        abort_unless($customer->canHaveWallet(), 404);
    }
}