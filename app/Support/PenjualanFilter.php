<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Penjualan;
use App\Models\RefundPenjualan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Satu definisi filter untuk halaman /penjualan: dipakai bersama oleh
 * tabel (server-side), kartu ringkasan, dan Cetak Rekap, sehingga angka
 * di ketiganya selalu berasal dari kumpulan nota yang sama.
 *
 * Aturan:
 *  - Default = HARI INI (tanggal kalender zona waktu aplikasi, K10).
 *  - staff-outlet / kasir DIPAKSA ke outlet sendiri di server (K16),
 *    bukan hanya disembunyikan di UI.
 *  - Penjualan marketplace (punya transaction) tidak ikut, sama seperti halaman lama.
 */
class PenjualanFilter
{
    public const PRESETS = [
        'today'      => 'Hari ini',
        'yesterday'  => 'Kemarin',
        'last7'      => '7 hari terakhir',
        'last30'     => '30 hari terakhir',
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'custom'     => 'Pilih tanggal',
    ];

    /** Peran yang hanya boleh melihat outlet sendiri. */
    public const OUTLET_BOUND_ROLES = ['staff-outlet', 'kasir'];

    public string $preset = 'today';
    public Carbon $start;
    public Carbon $end;
    public ?int $outletId = null;
    public ?int $kasirId = null;
    /** '' = semua, 'tunai' = tanpa metode terdaftar, angka = payment_methods.id */
    public string $method = '';
    /** all | normal | retur */
    public string $status = 'all';
    public string $search = '';

    public static function isOutletBound(?User $user): bool
    {
        return $user && in_array($user->role, self::OUTLET_BOUND_ROLES, true);
    }

    public static function fromRequest(Request $request): self
    {
        $f = new self();
        $today = now()->startOfDay();

        $preset = (string) $request->input('preset', '');
        if ($preset === '' && ($request->filled('tanggal_mulai') || $request->filled('tanggal_selesai'))) {
            $preset = 'custom';
        }
        if (! array_key_exists($preset, self::PRESETS)) {
            $preset = 'today';
        }

        switch ($preset) {
            case 'yesterday':
                $start = $today->copy()->subDay();
                $end = $start->copy();
                break;
            case 'last7':
                $start = $today->copy()->subDays(6);
                $end = $today->copy();
                break;
            case 'last30':
                $start = $today->copy()->subDays(29);
                $end = $today->copy();
                break;
            case 'this_month':
                $start = $today->copy()->startOfMonth();
                $end = $today->copy();
                break;
            case 'last_month':
                $start = $today->copy()->subMonthNoOverflow()->startOfMonth();
                $end = $start->copy()->endOfMonth();
                break;
            case 'custom':
                $start = self::parseDate($request->input('tanggal_mulai')) ?? $today->copy();
                $end = self::parseDate($request->input('tanggal_selesai')) ?? $start->copy();
                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }
                break;
            default: // today
                $start = $today->copy();
                $end = $today->copy();
        }

        $f->preset = $preset;
        $f->start = $start->copy()->startOfDay();
        $f->end = $end->copy()->endOfDay();

        // Outlet: role terikat outlet dipaksa ke outlet sendiri (K16).
        $user = $request->user();
        if (self::isOutletBound($user)) {
            $f->outletId = OutletAccess::id($request);
        } elseif ($request->filled('outlet_id')) {
            $outletId = (int) $request->input('outlet_id');
            abort_unless(Outlet::whereKey($outletId)->exists(), 404, 'Outlet tidak ditemukan.');
            $f->outletId = $outletId;
        }

        $f->kasirId = $request->filled('kasir_id') ? (int) $request->input('kasir_id') : null;

        $method = trim((string) $request->input('metode', ''));
        $f->method = ($method === 'tunai' || ctype_digit($method)) ? $method : '';

        $status = (string) $request->input('status', 'all');
        $f->status = in_array($status, ['normal', 'retur'], true) ? $status : 'all';

        $f->search = trim((string) $request->input('q', ''));

        return $f;
    }

    protected static function parseDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Terapkan filter ke query Penjualan. */
    public function apply(Builder $query): Builder
    {
        $query->doesntHave('transaction')
            ->where('penjualans.created_at', '>=', $this->start)
            ->where('penjualans.created_at', '<=', $this->end);

        if ($this->outletId) {
            $query->where('penjualans.outlet_id', $this->outletId);
        }

        if ($this->kasirId) {
            // kasir_id bertipe string di database, bandingkan sebagai string
            $query->where('penjualans.kasir_id', (string) $this->kasirId);
        }

        if ($this->method === 'tunai') {
            $query->whereNull('penjualans.payment_method_id');
        } elseif ($this->method !== '') {
            $query->where('penjualans.payment_method_id', (int) $this->method);
        }

        if ($this->status === 'retur') {
            $query->has('refundPenjualans');
        } elseif ($this->status === 'normal') {
            $query->doesntHave('refundPenjualans');
        }

        if ($this->search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('penjualans.code', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('users')
                            ->whereColumn('users.id', 'penjualans.kasir_id')
                            ->where('users.name', 'like', $like);
                    })
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('users')
                            ->whereColumn('users.id', 'penjualans.customer_id')
                            ->where('users.name', 'like', $like);
                    })
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))->from('penjualan_items')
                            ->join('products', 'products.id', '=', 'penjualan_items.product_id')
                            ->whereColumn('penjualan_items.penjualan_id', 'penjualans.id')
                            ->whereNull('penjualan_items.deleted_at')
                            ->where(function ($w) use ($like) {
                                $w->where('products.name', 'like', $like)
                                    ->orWhere('products.code', 'like', $like);
                            });
                    });
            });
        }

        return $query;
    }

    /** Query Penjualan yang sudah difilter. */
    public function query(): Builder
    {
        return $this->apply(Penjualan::query());
    }

    /**
     * Angka ringkasan (satu query agregat + satu per metode bayar + satu untuk retur).
     * Nilai nota memakai nilai tercatat di nota (grand_total); retur ditampilkan
     * terpisah supaya jelas, bukan dicampur diam-diam.
     */
    public function summary(): array
    {
        $grand = 'COALESCE(penjualans.grand_total, penjualans.total - COALESCE(penjualans.discount, 0), 0)';

        $row = $this->query()
            ->selectRaw('COUNT(*) AS trx')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.subtotal, penjualans.total, 0)), 0) AS subtotal')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.discount_total, penjualans.discount, 0)), 0) AS diskon')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.promotion_total, 0)), 0) AS promo')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.voucher_total, 0)), 0) AS voucher')
            ->selectRaw("COALESCE(SUM($grand), 0) AS grand")
            ->first();

        $methods = $this->query()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'penjualans.payment_method_id')
            ->selectRaw("COALESCE(payment_methods.name, penjualans.payment_method_name, 'Tunai') AS metode")
            ->selectRaw('COUNT(*) AS trx')
            ->selectRaw("COALESCE(SUM($grand), 0) AS total")
            ->groupBy(DB::raw("COALESCE(payment_methods.name, penjualans.payment_method_name, 'Tunai')"))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($m) => [
                'metode' => $m->metode,
                'trx'    => (int) $m->trx,
                'total'  => (float) $m->total,
            ])->all();

        $retur = RefundPenjualan::query()
            ->whereIn('penjualan_id', $this->query()->select('penjualans.id'))
            ->where('created_at', '<=', $this->end)
            ->selectRaw('COUNT(*) AS jumlah')
            ->selectRaw('COUNT(DISTINCT penjualan_id) AS nota')
            ->selectRaw('COALESCE(SUM(returned_total), 0) AS retur')
            ->selectRaw('COALESCE(SUM(replacement_total), 0) AS pengganti')
            ->first();

        $subtotal = (float) $row->subtotal;
        $potongan = (float) $row->diskon + (float) $row->promo + (float) $row->voucher;

        return [
            'transaksi' => (int) $row->trx,
            'subtotal'  => $subtotal,
            'diskon'    => (float) $row->diskon,
            'promo'     => (float) $row->promo,
            'voucher'   => (float) $row->voucher,
            'potongan'  => $potongan,
            'grand'     => (float) $row->grand,
            'metode'    => $methods,
            'retur'     => [
                'jumlah'    => (int) ($retur->jumlah ?? 0),
                'nota'      => (int) ($retur->nota ?? 0),
                'nilai'     => (float) ($retur->retur ?? 0),
                'pengganti' => (float) ($retur->pengganti ?? 0),
            ],
        ];
    }

    /** Ringkasan per kasir dan per outlet untuk Cetak Rekap (Ringkas). */
    public function breakdown(string $by): array
    {
        $grand = 'COALESCE(penjualans.grand_total, penjualans.total - COALESCE(penjualans.discount, 0), 0)';
        $q = $this->query();

        if ($by === 'shift') {
            $q->leftJoin('cashier_shifts', 'cashier_shifts.id', '=', 'penjualans.cashier_shift_id')
                ->selectRaw("COALESCE(cashier_shifts.name, '-') AS nama")
                ->groupBy(DB::raw("COALESCE(cashier_shifts.name, '-')"));
        } elseif ($by === 'kasir') {
            $q->leftJoin('users', 'users.id', '=', 'penjualans.kasir_id')
                ->selectRaw("COALESCE(users.name, '-') AS nama")
                ->groupBy(DB::raw("COALESCE(users.name, '-')"));
        } else {
            $q->leftJoin('outlets', 'outlets.id', '=', 'penjualans.outlet_id')
                ->selectRaw("COALESCE(outlets.name, '-') AS nama")
                ->groupBy(DB::raw("COALESCE(outlets.name, '-')"));
        }

        return $q->selectRaw('COUNT(*) AS trx')
            ->selectRaw("COALESCE(SUM($grand), 0) AS total")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['nama' => $r->nama, 'trx' => (int) $r->trx, 'total' => (float) $r->total])
            ->all();
    }

    /** Pilihan untuk dropdown filter. */
    public function options(?User $user): array
    {
        $outlets = OutletAccess::outlets();

        $kasirs = User::query()
            ->whereIn('role', ['kasir', 'staff-outlet', 'owner', 'admin', 'superadmin'])
            ->when(self::isOutletBound($user), fn ($q) => $q->where('outlet_id', $user->outlet_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'outlets' => $outlets,
            'kasirs'  => $kasirs,
            'methods' => PaymentMethod::orderBy('name')->get(['id', 'name']),
        ];
    }

    /** Parameter filter untuk URL (bookmark/bagikan, Cetak Rekap, dll). */
    public function toParams(): array
    {
        return array_filter([
            'preset'          => $this->preset,
            'tanggal_mulai'   => $this->preset === 'custom' ? $this->start->toDateString() : null,
            'tanggal_selesai' => $this->preset === 'custom' ? $this->end->toDateString() : null,
            'outlet_id'       => $this->outletId,
            'kasir_id'        => $this->kasirId,
            'metode'          => $this->method !== '' ? $this->method : null,
            'status'          => $this->status !== 'all' ? $this->status : null,
            'q'               => $this->search !== '' ? $this->search : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function periodLabel(): string
    {
        return $this->start->isSameDay($this->end)
            ? $this->start->format('d/m/Y')
            : $this->start->format('d/m/Y').' s/d '.$this->end->format('d/m/Y');
    }
}