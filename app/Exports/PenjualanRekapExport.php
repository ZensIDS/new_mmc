<?php

namespace App\Exports;

use App\Exports\Support\BaseReportExport as Base;
use App\Exports\Support\TableReportSheet;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\PenjualanFilter;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Rekap Penjualan (halaman /penjualan) — memakai filter aktif, dua sheet:
 *   1. "Ringkasan" : total, per metode bayar, per kasir, per outlet, retur, bon, setoran.
 *   2. "Transaksi" : satu baris per nota (angka & tanggal asli, bisa difilter/pivot), baris TOTAL berumus.
 * Kerangka tampilan sama dengan laporan lain (BaseReportExport).
 */
class PenjualanRekapExport implements WithMultipleSheets
{
    /** Batas aman jumlah nota per unduhan. */
    public const MAX_ROWS = 50000;

    public function __construct(
        private readonly PenjualanFilter $filter,
        private readonly float $bon = 0,
        private readonly float $setoran = 0,
    ) {}

    public function fileName(): string
    {
        return Base::fileName('Rekap-Penjualan', $this->filter->start, $this->filter->end);
    }

    public function sheets(): array
    {
        $f = $this->filter;
        $period = Base::periodLabel($f->start, $f->end);
        $filters = $this->filterTexts();

        $summary = new TableReportSheet(
            'Rekap Penjualan',
            [
                Base::col('Uraian', 'text', 42),
                Base::col('Transaksi', 'int', 12),
                Base::col('Nilai', 'rupiah', 20),
            ],
            function () use ($f) {
                $s = $f->summary();
                $rows = [
                    ['RINGKASAN', null, null],
                    ['Jumlah transaksi', $s['transaksi'], null],
                    ['Total penjualan (sebelum potongan)', null, $s['subtotal']],
                    ['   Diskon toko', null, $s['diskon']],
                    ['   Promo', null, $s['promo']],
                    ['   Voucher', null, $s['voucher']],
                    ['Grand Total (nilai nota, sebelum retur)', null, $s['grand']],
                    [null, null, null],
                    ['PER METODE PEMBAYARAN', null, null],
                ];
                foreach ($s['metode'] as $m) {
                    $rows[] = ['   '.$m['metode'], $m['trx'], $m['total']];
                }

                // Per kasir: dikelompokkan per nama shift.
                $rows[] = [null, null, null];
                $rows[] = ['PER KASIR (NAMA SHIFT)', null, null];
                foreach ($f->breakdown('shift') as $r) {
                    $rows[] = ['   '.$r['nama'], $r['trx'], $r['total']];
                }

                $rows[] = [null, null, null];
                $rows[] = ['RETUR / GANTI BARANG (nota pada periode ini)', null, null];
                $rows[] = ['   Jumlah retur', $s['retur']['jumlah'], null];
                $rows[] = ['   Nilai barang diretur', null, $s['retur']['nilai']];
                $rows[] = ['   Nilai barang pengganti', null, $s['retur']['pengganti']];

                $rows[] = [null, null, null];
                $rows[] = ['KAS (periode, outlet & kasir; tidak mengikuti filter metode/status/cari)', null, null];
                $rows[] = ['   Bon / kas keluar', null, $this->bon];
                $rows[] = ['   Setoran akhir', null, $this->setoran];

                // Kolom Nilai tidak pernah kosong pada baris data -> 0 (tampil "Rp 0").
                // Judul seksi (baris tanpa angka sama sekali) & baris pemisah tetap kosong.
                return array_map(function ($r) {
                    $isHeading = $r[1] === null && $r[2] === null; // judul seksi
                    if ($r[0] !== null && $r[2] === null && ! $isHeading) {
                        $r[2] = 0;
                    }

                    return $r;
                }, $rows);
            },
            ['sheet' => 'Ringkasan', 'period' => $period, 'filters' => $filters]
        );

        $detail = new TableReportSheet(
            'Daftar Transaksi Penjualan',
            [
                Base::col('No', 'no'),
                Base::col('Tanggal', 'datetime', 17),
                Base::col('Invoice', 'code', 22),
                Base::col('Customer', 'text', 22),
                Base::col('Outlet', 'text', 20),
                Base::col('Kasir', 'text', 18),
                Base::col('Shift', 'text', 14),
                Base::col('Item', 'int', 8),
                Base::col('Metode Bayar', 'text', 18),
                Base::col('Subtotal', 'money', 16, ['sum' => true]),
                Base::col('Potongan', 'money', 15, ['sum' => true]),
                Base::col('Grand Total', 'money', 17, ['sum' => true]),
                Base::col('Retur', 'center', 9),
            ],
            function () use ($f) {
                $query = $f->query()
                    ->with(['outlet', 'kasir', 'cashierShift', 'paymentMethod', 'customer'])
                    ->withCount(['items', 'refundPenjualans'])
                    ->orderBy('penjualans.created_at')->orderBy('penjualans.id');

                foreach ($query->lazy(500) as $p) {
                    yield [
                        $p->created_at,
                        $p->code,
                        $p->customer?->name ?? 'Umum',
                        $p->outlet?->name ?? '-',
                        $p->kasir?->name ?? '-',
                        $p->cashierShift?->name ?? '-',
                        $p->items_count,
                        $p->paymentMethod?->name ?? $p->payment_method_name ?? 'Tunai',
                        (float) ($p->subtotal ?? $p->total ?? 0),
                        (float) ($p->discount_total ?? $p->discount ?? 0)
                            + (float) ($p->promotion_total ?? 0)
                            + (float) ($p->voucher_total ?? 0),
                        (float) ($p->grand_total ?? ((float) $p->total - (float) $p->discount)),
                        $p->refund_penjualans_count > 0 ? 'Ya' : null,
                    ];
                }
            },
            ['sheet' => 'Transaksi', 'period' => $period, 'filters' => $filters, 'logo' => false]
        );

        return [$summary, $detail];
    }

    /** @return string[] */
    private function filterTexts(): array
    {
        $f = $this->filter;

        $texts = [
            'Outlet: '.($f->outletId ? (Outlet::find($f->outletId)?->name ?? '-') : 'Semua Outlet'),
            'Kasir: '.($f->kasirId ? (User::find($f->kasirId)?->name ?? '-') : 'Semua Kasir'),
        ];

        if ($f->method !== '') {
            $texts[] = 'Metode: '.($f->method === 'tunai'
                ? 'Tunai (tanpa metode terdaftar)'
                : (PaymentMethod::withTrashed()->find($f->method)?->name ?? '-'));
        }
        if ($f->status !== 'all') {
            $texts[] = 'Status: '.($f->status === 'retur' ? 'Ada retur' : 'Normal');
        }
        if ($f->search !== '') {
            $texts[] = 'Pencarian: '.$f->search;
        }

        return $texts;
    }
}