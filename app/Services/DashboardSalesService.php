<?php

namespace App\Services;

use App\Models\Penjualan;
use App\Models\RefundPenjualan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Angka penjualan untuk dashboard (Tahap 3).
 *
 * Aturan hitung (disamakan dengan Laporan Penjualan Outlet):
 *   Omzet bersih = nilai nota berstatus paid/null di periode
 *                  - nilai barang yang diretur dari nota-nota itu (retur dibuat s/d akhir periode)
 *                  + nilai barang pengganti pada retur yang dibuat di periode.
 *   Jumlah transaksi = jumlah nota + jumlah retur yang punya barang pengganti.
 *
 * Semua agregasi dihitung di SQL (SUM/COUNT/GROUP BY) dengan rentang
 * `created_at >= ? AND <= ?` (tanpa whereDate) supaya index dipakai.
 *
 * Catatan perbedaan kecil yang diketahui: Laporan mengalokasikan voucher per item
 * lalu mengurangi proporsional saat retur; dashboard memakai returned_total yang
 * tercatat di retur. Selisih hanya muncul bila retur terjadi pada nota ber-voucher.
 */
class DashboardSalesService
{
    public const CACHE_SECONDS = 120;

    /** Setelah batas ini grafik tren dikelompokkan per bulan, bukan per hari. */
    public const MAX_DAILY_POINTS = 62;

    /** Batas rentang tanggal yang boleh diminta (hari). */
    public const MAX_RANGE_DAYS = 366;

    protected const GRAND = 'COALESCE(penjualans.grand_total, penjualans.total - COALESCE(penjualans.discount, 0), 0)';

    protected const METHOD = "COALESCE(payment_methods.name, penjualans.payment_method_name, 'Tunai')";

    /**
     * Payload lengkap untuk dashboard. Di-cache pendek per (periode, outlet).
     */
    public function payload(Carbon $start, Carbon $end, ?int $outletId, bool $fresh = false): array
    {
        $key = 'dashboard.sales.'.md5($start->toDateTimeString().'|'.$end->toDateTimeString().'|'.($outletId ?? 'all'));

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_SECONDS, fn () => $this->build($start, $end, $outletId));
    }

    protected function build(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $yStart = $todayStart->copy()->subDay();
        $yEnd = $yStart->copy()->endOfDay();

        $days = (int) abs($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay())) + 1;
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($days)->startOfDay();

        $period = $this->totals($start, $end, $outletId);
        $previous = $this->totals($prevStart, $prevEnd, $outletId);
        $today = $this->totals($todayStart, $todayEnd, $outletId);
        $yesterday = $this->totals($yStart, $yEnd, $outletId);

        $perMonth = $days > self::MAX_DAILY_POINTS;

        return [
            'periode' => [
                'mulai'   => $start->toDateString(),
                'selesai' => $end->toDateString(),
                'label'   => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y').' s/d '.$end->format('d/m/Y'),
                'hari'    => $days,
                'per_bulan' => $perMonth,
            ],
            'cards' => [
                'hari_ini' => [
                    'omzet'     => $today['net'],
                    'transaksi' => $today['trx'],
                    'delta'     => $this->delta($today['net'], $yesterday['net']),
                    'kemarin'   => $yesterday['net'],
                ],
                'periode' => [
                    'omzet'     => $period['net'],
                    'transaksi' => $period['trx'],
                    'rata_rata' => $period['trx'] > 0 ? round($period['net'] / $period['trx'], 2) : 0,
                    'potongan'  => $period['potongan'],
                    'retur'     => $period['returned'],
                    'pengganti' => $period['replacement'],
                    'delta'     => $this->delta($period['net'], $previous['net']),
                    'sebelumnya' => $previous['net'],
                    'delta_trx' => $this->delta($period['trx'], $previous['trx']),
                ],
            ],
            'tren'   => $this->trend($start, $end, $outletId, $perMonth),
            'outlet' => $this->byOutlet($start, $end, $outletId),
            'metode' => $this->byMethod($start, $end, $outletId),
            'jam'    => $this->byHour($start, $end, $outletId),
            'top_produk' => $this->topProducts($start, $end, $outletId),
            'top_kasir'  => $this->topCashiers($start, $end, $outletId),
            'terbaru'    => $this->recent($start, $end, $outletId),
            'dibuat'     => $now->format('d/m/Y H:i:s'),
        ];
    }

    /* ------------------------------------------------------------------
     |  Query dasar
     | ------------------------------------------------------------------ */

    /** Nota yang dihitung sebagai penjualan (sama dengan OutletLaporanService::saleQuery). */
    protected function sales(Carbon $start, Carbon $end, ?int $outletId): Builder
    {
        return Penjualan::query()
            ->where(function ($q) {
                $q->where('penjualans.status', 'paid')->orWhereNull('penjualans.status');
            })
            ->where('penjualans.created_at', '>=', $start)
            ->where('penjualans.created_at', '<=', $end)
            ->when($outletId, fn ($q) => $q->where('penjualans.outlet_id', $outletId));
    }

    /** Retur atas nota-nota di periode (barang yang dikurangkan dari omzet). */
    protected function returnsOfSales(Carbon $start, Carbon $end, ?int $outletId): Builder
    {
        return RefundPenjualan::query()
            ->join('penjualans', 'penjualans.id', '=', 'refund_penjualans.penjualan_id')
            ->whereNull('penjualans.deleted_at')
            ->where(function ($q) {
                $q->where('penjualans.status', 'paid')->orWhereNull('penjualans.status');
            })
            ->where('penjualans.created_at', '>=', $start)
            ->where('penjualans.created_at', '<=', $end)
            ->where('refund_penjualans.created_at', '<=', $end)
            ->when($outletId, fn ($q) => $q->where('penjualans.outlet_id', $outletId));
    }

    /** Retur yang punya barang pengganti, dibuat di periode (dihitung sebagai penjualan). */
    protected function replacements(Carbon $start, Carbon $end, ?int $outletId): Builder
    {
        return RefundPenjualan::query()
            ->leftJoin('penjualans', 'penjualans.id', '=', 'refund_penjualans.penjualan_id')
            ->where('refund_penjualans.created_at', '>=', $start)
            ->where('refund_penjualans.created_at', '<=', $end)
            ->where('refund_penjualans.replacement_total', '>', 0)
            ->when($outletId, fn ($q) => $q->where('refund_penjualans.outlet_id', $outletId));
    }

    /* ------------------------------------------------------------------
     |  Total
     | ------------------------------------------------------------------ */

    /** @return array{net: float, gross: float, returned: float, replacement: float, trx: int, potongan: float} */
    public function totals(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $g = $this->sales($start, $end, $outletId)
            ->selectRaw('COUNT(*) AS trx')
            ->selectRaw('COALESCE(SUM('.self::GRAND.'), 0) AS gross')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.discount_total, penjualans.discount, 0)), 0) AS diskon')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.promotion_total, 0)), 0) AS promo')
            ->selectRaw('COALESCE(SUM(COALESCE(penjualans.voucher_total, 0)), 0) AS voucher')
            ->first();

        $returned = (float) $this->returnsOfSales($start, $end, $outletId)
            ->sum('refund_penjualans.returned_total');

        $r = $this->replacements($start, $end, $outletId)
            ->selectRaw('COUNT(*) AS trx')
            ->selectRaw('COALESCE(SUM(refund_penjualans.replacement_total), 0) AS total')
            ->first();

        $gross = (float) $g->gross;
        $replacement = (float) $r->total;

        return [
            'gross'       => $gross,
            'returned'    => $returned,
            'replacement' => $replacement,
            'net'         => max(0, $gross - $returned + $replacement),
            'trx'         => (int) $g->trx + (int) $r->trx,
            'potongan'    => (float) $g->diskon + (float) $g->promo + (float) $g->voucher,
        ];
    }

    /** Persen perubahan; null bila pembanding 0 (tidak bisa dihitung). */
    protected function delta(float|int $now, float|int $before): ?float
    {
        if ((float) $before <= 0) {
            return null;
        }

        return round((($now - $before) / $before) * 100, 1);
    }

    /* ------------------------------------------------------------------
     |  Deret per dimensi (hari/outlet/metode/jam/kasir)
     | ------------------------------------------------------------------ */

    /**
     * Omzet bersih & jumlah transaksi per kunci.
     *
     * $saleKey  = ekspresi SQL berbasis tabel penjualans (+ payment_methods / users bila diminta)
     * $replKey  = ekspresi SQL untuk retur pengganti (berbasis refund_penjualans / penjualans)
     * $joins    = ['pm'] dan/atau ['user'] -> join tambahan pada penjualans
     *
     * @return array<string, array{net: float, trx: int}>
     */
    protected function series(Carbon $start, Carbon $end, ?int $outletId, string $saleKey, string $replKey, array $joins = []): array
    {
        $out = [];
        $bump = function (string $k, float $net, int $trx) use (&$out) {
            $out[$k] ??= ['net' => 0.0, 'trx' => 0];
            $out[$k]['net'] += $net;
            $out[$k]['trx'] += $trx;
        };
        $applyJoins = function (Builder $q) use ($joins) {
            if (in_array('pm', $joins, true)) {
                $q->leftJoin('payment_methods', 'payment_methods.id', '=', 'penjualans.payment_method_id');
            }
            if (in_array('user', $joins, true)) {
                $q->leftJoin('users', 'users.id', '=', 'penjualans.kasir_id');
            }

            return $q;
        };

        $gross = $applyJoins($this->sales($start, $end, $outletId))
            ->selectRaw("$saleKey AS k")
            ->selectRaw('COUNT(*) AS trx')
            ->selectRaw('COALESCE(SUM('.self::GRAND.'), 0) AS gross')
            ->groupBy(DB::raw($saleKey))
            ->get();
        foreach ($gross as $row) {
            $bump((string) $row->k, (float) $row->gross, (int) $row->trx);
        }

        $returned = $applyJoins($this->returnsOfSales($start, $end, $outletId))
            ->selectRaw("$saleKey AS k")
            ->selectRaw('COALESCE(SUM(refund_penjualans.returned_total), 0) AS total')
            ->groupBy(DB::raw($saleKey))
            ->get();
        foreach ($returned as $row) {
            $bump((string) $row->k, -(float) $row->total, 0);
        }

        $repl = $applyJoins($this->replacements($start, $end, $outletId))
            ->selectRaw("$replKey AS k")
            ->selectRaw('COUNT(*) AS trx')
            ->selectRaw('COALESCE(SUM(refund_penjualans.replacement_total), 0) AS total')
            ->groupBy(DB::raw($replKey))
            ->get();
        foreach ($repl as $row) {
            $bump((string) $row->k, (float) $row->total, (int) $row->trx);
        }

        return $out;
    }

    /** Tren omzet per hari (atau per bulan bila rentang panjang), hari kosong diisi 0. */
    protected function trend(Carbon $start, Carbon $end, ?int $outletId, bool $perMonth): array
    {
        $fmt = $perMonth ? "DATE_FORMAT(%s, '%%Y-%%m')" : 'DATE(%s)';
        $data = $this->series(
            $start, $end, $outletId,
            sprintf($fmt, 'penjualans.created_at'),
            sprintf($fmt, 'refund_penjualans.created_at')
        );

        $labels = [];
        $net = [];
        $trx = [];
        $cursor = $perMonth ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $last = $perMonth ? $end->copy()->startOfMonth() : $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $key = $perMonth ? $cursor->format('Y-m') : $cursor->toDateString();
            $labels[] = $perMonth ? $cursor->translatedFormat('M Y') : $cursor->format('d/m');
            $net[] = round(max(0, $data[$key]['net'] ?? 0), 2);
            $trx[] = (int) ($data[$key]['trx'] ?? 0);
            $perMonth ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return ['labels' => $labels, 'omzet' => $net, 'transaksi' => $trx];
    }

    protected function byOutlet(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $data = $this->series($start, $end, $outletId, 'penjualans.outlet_id', 'refund_penjualans.outlet_id');
        if (! $data) {
            return [];
        }

        $names = DB::table('outlets')->whereIn('id', array_keys($data))->pluck('name', 'id');

        $rows = [];
        foreach ($data as $id => $v) {
            $rows[] = [
                'nama'      => $names[$id] ?? 'Tanpa outlet',
                'omzet'     => round(max(0, $v['net']), 2),
                'transaksi' => $v['trx'],
            ];
        }
        usort($rows, fn ($a, $b) => $b['omzet'] <=> $a['omzet']);

        return array_slice($rows, 0, 15);
    }

    protected function byMethod(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $data = $this->series(
            $start, $end, $outletId,
            self::METHOD,
            "COALESCE(NULLIF(refund_penjualans.payment_method_name, ''), ".self::METHOD.')',
            ['pm']
        );

        $rows = [];
        foreach ($data as $name => $v) {
            if ($v['net'] <= 0) {
                continue;
            }
            $rows[] = ['nama' => $name, 'omzet' => round($v['net'], 2), 'transaksi' => $v['trx']];
        }
        usort($rows, fn ($a, $b) => $b['omzet'] <=> $a['omzet']);

        return $rows;
    }

    /** Jam ramai: omzet dan jumlah transaksi per jam (0-23). */
    protected function byHour(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $data = $this->series($start, $end, $outletId, 'HOUR(penjualans.created_at)', 'HOUR(refund_penjualans.created_at)');

        $labels = [];
        $omzet = [];
        $trx = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $omzet[] = round(max(0, $data[(string) $h]['net'] ?? 0), 2);
            $trx[] = (int) ($data[(string) $h]['trx'] ?? 0);
        }

        return ['labels' => $labels, 'omzet' => $omzet, 'transaksi' => $trx];
    }

    /* ------------------------------------------------------------------
     |  Ranking
     | ------------------------------------------------------------------ */

    /**
     * Top 10 produk terjual. Qty dikurangi retur yang tertaut ke item nota
     * (retur tanpa penjualan_item_id tidak bisa dialokasikan dengan SQL murni,
     * jadi tidak ikut mengurangi di sini).
     */
    protected function topProducts(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $returned = DB::table('refund_penjualan_items')
            ->join('refund_penjualans', 'refund_penjualans.id', '=', 'refund_penjualan_items.refund_penjualan_id')
            ->whereNull('refund_penjualans.deleted_at')
            ->where('refund_penjualan_items.type', 'return')
            ->whereNotNull('refund_penjualan_items.penjualan_item_id')
            ->where('refund_penjualans.created_at', '<=', $end)
            ->groupBy('refund_penjualan_items.penjualan_item_id')
            ->selectRaw('refund_penjualan_items.penjualan_item_id AS item_id, SUM(refund_penjualan_items.qty) AS ret_qty');

        $net = 'GREATEST(penjualan_items.qty - COALESCE(r.ret_qty, 0), 0)';

        return $this->sales($start, $end, $outletId)
            ->join('penjualan_items', 'penjualan_items.penjualan_id', '=', 'penjualans.id')
            ->whereNull('penjualan_items.deleted_at')
            ->leftJoinSub($returned, 'r', 'r.item_id', '=', 'penjualan_items.id')
            ->leftJoin('products', 'products.id', '=', 'penjualan_items.product_id')
            ->groupBy('penjualan_items.product_id', 'products.name', 'products.code')
            ->selectRaw("COALESCE(products.name, '-') AS nama")
            ->selectRaw("COALESCE(products.code, '') AS kode")
            ->selectRaw("COALESCE(SUM($net), 0) AS qty")
            ->selectRaw("COALESCE(SUM(penjualan_items.price * $net), 0) AS omzet")
            ->havingRaw("SUM($net) > 0")
            ->orderByDesc('omzet')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'nama'  => $r->nama,
                'kode'  => $r->kode,
                'qty'   => (int) $r->qty,
                'omzet' => (float) $r->omzet,
            ])->all();
    }

    protected function topCashiers(Carbon $start, Carbon $end, ?int $outletId): array
    {
        $data = $this->series(
            $start, $end, $outletId,
            "COALESCE(users.name, '-')",
            "COALESCE(users.name, '-')",
            ['user']
        );

        $rows = [];
        foreach ($data as $name => $v) {
            $rows[] = [
                'nama'      => $name,
                'omzet'     => round(max(0, $v['net']), 2),
                'transaksi' => $v['trx'],
            ];
        }
        usort($rows, fn ($a, $b) => $b['omzet'] <=> $a['omzet']);

        return array_slice($rows, 0, 10);
    }

    /** 10 nota terbaru pada periode. */
    protected function recent(Carbon $start, Carbon $end, ?int $outletId): array
    {
        return $this->sales($start, $end, $outletId)
            ->leftJoin('outlets', 'outlets.id', '=', 'penjualans.outlet_id')
            ->leftJoin('users', 'users.id', '=', 'penjualans.kasir_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'penjualans.payment_method_id')
            ->select('penjualans.id', 'penjualans.code', 'penjualans.created_at')
            ->selectRaw("COALESCE(outlets.name, '-') AS outlet")
            ->selectRaw("COALESCE(users.name, '-') AS kasir")
            ->selectRaw(self::METHOD.' AS metode')
            ->selectRaw(self::GRAND.' AS total')
            ->orderByDesc('penjualans.created_at')
            ->orderByDesc('penjualans.id')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'kode'   => $r->code,
                'waktu'  => Carbon::parse($r->created_at)->format('d/m H:i'),
                'outlet' => $r->outlet,
                'kasir'  => $r->kasir,
                'metode' => $r->metode,
                'total'  => (float) $r->total,
            ])->all();
    }
}