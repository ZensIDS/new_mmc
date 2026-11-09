<?php

namespace App\Http\Controllers;

use App\Models\NotaFiktif;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Nota Fiktif — HANYA superadmin.
 * Hanya menulis ke tabel nota_fiktifs & nota_fiktif_items. Tidak menyentuh stok, penjualan,
 * kas, cashier session, maupun laporan apa pun.
 */
class NotaFiktifController extends Controller
{
    public function __construct()
    {
        // Lapis kedua selain middleware di routes/web.php.
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->check() && auth()->user()->isSuperadmin(), 403, 'Menu ini khusus superadmin.');

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $notas = NotaFiktif::query()
            ->withCount('items')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                        ->orWhere('customer_nama', 'like', "%{$q}%")
                        ->orWhere('outlet_nama', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('dari'), fn ($query) => $query->whereDate('tanggal', '>=', $request->query('dari')))
            ->when($request->filled('sampai'), fn ($query) => $query->whereDate('tanggal', '<=', $request->query('sampai')))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('nota-fiktif.index', compact('notas', 'q'));
    }

    public function create()
    {
        return view('nota-fiktif.create', $this->formData(new NotaFiktif([
            'tanggal' => now(),
            'kasir_nama' => auth()->user()->name,
            'metode_bayar' => 'Tunai',
            'footer' => "Harga Barang Sudah termasuk PPN\nTerima Kasih Atas Kunjungan Anda",
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $nota = DB::transaction(function () use ($data) {
            $nota = NotaFiktif::create($this->header($data) + ['created_by' => auth()->id()]);
            $this->syncItems($nota, $data['items']);

            return $nota;
        });

        return redirect()->route('nota-fiktif.show', $nota)->with('toast_success', 'Nota fiktif tersimpan.');
    }

    public function show(NotaFiktif $notaFiktif)
    {
        $notaFiktif->load('items');

        // Default = faktur continuous form (sama seperti faktur penjualan); paper=58|80 = struk thermal.
        $view = in_array(request('paper'), ['58', '80'], true) ? 'nota-fiktif.print' : 'nota-fiktif.print-faktur';

        return view($view, ['nota' => $notaFiktif]);
    }

    public function edit(NotaFiktif $notaFiktif)
    {
        $notaFiktif->load('items');

        return view('nota-fiktif.edit', $this->formData($notaFiktif));
    }

    public function update(Request $request, NotaFiktif $notaFiktif)
    {
        $data = $this->validated($request, $notaFiktif->id);

        DB::transaction(function () use ($data, $notaFiktif) {
            $notaFiktif->update($this->header($data));
            $notaFiktif->items()->delete();
            $this->syncItems($notaFiktif, $data['items']);
        });

        return redirect()->route('nota-fiktif.show', $notaFiktif)->with('toast_success', 'Nota fiktif diperbarui.');
    }

    public function destroy(NotaFiktif $notaFiktif)
    {
        $notaFiktif->delete(); // soft delete, hanya mempengaruhi arsip nota fiktif

        return redirect()->route('nota-fiktif.index')->with('toast_success', 'Nota fiktif dihapus.');
    }

    /** Pencarian barang untuk form (hanya BACA tabel produk). */
    public function products(Request $request)
    {
        $words = preg_split('/\s+/', trim((string) $request->query('q', '')), -1, PREG_SPLIT_NO_EMPTY);

        if (! $words) {
            return response()->json([]);
        }

        $rows = Product::query()
            ->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->where(function ($w) use ($word) {
                        $w->where('name', 'like', "%{$word}%")->orWhere('code', 'like', "%{$word}%");
                    });
                }
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name', 'satuan', 'harga_jual']);

        return response()->json($rows->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'satuan' => $p->satuan,
            'harga' => (float) ($p->harga_jual ?? 0),
        ])->values());
    }

    private function formData(NotaFiktif $nota): array
    {
        return [
            'nota' => $nota,
            'outlets' => Outlet::orderBy('name')->get(['id', 'name', 'alamat', 'footer']),
            'customers' => User::where('role', 'customer')->orderBy('name')->limit(1000)->pluck('name'),
            'payments' => PaymentMethod::orderBy('name')->pluck('name'),
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:60', 'unique:nota_fiktifs,code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'tanggal' => ['required', 'date'],
            'outlet_nama' => ['required', 'string', 'max:255'],
            'outlet_alamat' => ['nullable', 'string', 'max:1000'],
            'customer_nama' => ['nullable', 'string', 'max:255'],
            'kasir_nama' => ['nullable', 'string', 'max:255'],
            'metode_bayar' => ['nullable', 'string', 'max:255'],
            'referensi' => ['nullable', 'string', 'max:255'],
            'diskon' => ['nullable', 'numeric', 'min:0'],
            'bayar' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'footer' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.nama' => ['required', 'string', 'max:255'],
            'items.*.satuan' => ['nullable', 'string', 'max:40'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
        ], [
            'items.required' => 'Minimal pilih 1 barang.',
            'items.min' => 'Minimal pilih 1 barang.',
            'items.*.nama.required' => 'Nama barang wajib diisi.',
            'items.*.qty.gt' => 'Qty harus lebih dari 0.',
        ]);
    }

    /** Hitung total di server (bukan percaya angka dari browser). */
    private function header(array $data): array
    {
        $subtotal = collect($data['items'])->sum(fn ($i) => round((float) $i['qty'] * (float) $i['harga'], 2));
        $diskon = min((float) ($data['diskon'] ?? 0), $subtotal);
        $total = $subtotal - $diskon;
        $bayar = array_key_exists('bayar', $data) && $data['bayar'] !== null ? (float) $data['bayar'] : $total;

        return [
            'code' => trim((string) ($data['code'] ?? '')) !== '' ? trim($data['code']) : $this->nextCode(),
            'tanggal' => $data['tanggal'],
            'outlet_nama' => $data['outlet_nama'],
            'outlet_alamat' => $data['outlet_alamat'] ?? null,
            'customer_nama' => $data['customer_nama'] ?? null,
            'kasir_nama' => $data['kasir_nama'] ?? null,
            'metode_bayar' => $data['metode_bayar'] ?? null,
            'referensi' => $data['referensi'] ?? null,
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'total' => $total,
            'bayar' => $bayar,
            'kembali' => max(0, $bayar - $total),
            'catatan' => $data['catatan'] ?? null,
            'footer' => $data['footer'] ?? null,
        ];
    }

    private function syncItems(NotaFiktif $nota, array $items): void
    {
        foreach ($items as $item) {
            $qty = (float) $item['qty'];
            $harga = (float) $item['harga'];

            $nota->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'nama' => $item['nama'],
                'satuan' => $item['satuan'] ?? null,
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => round($qty * $harga, 2),
            ]);
        }
    }

    private function nextCode(): string
    {
        $prefix = 'NF-' . now()->format('Ymd') . '-';
        $n = NotaFiktif::withTrashed()->where('code', 'like', $prefix . '%')->count() + 1;

        do {
            $code = $prefix . str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (NotaFiktif::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}