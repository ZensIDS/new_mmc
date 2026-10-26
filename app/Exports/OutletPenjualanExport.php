<?php

namespace App\Exports;

use App\Exports\Support\BaseReportExport as Base;
use App\Exports\Support\TableReportSheet;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan Penjualan Outlet — dua sheet:
 *   1. "Penjualan" : satu baris per item (angka & tanggal asli, tanpa rowspan
 *                    sehingga bisa difilter/pivot), baris TOTAL berumus.
 *   2. "Rekap"     : total penjualan, per metode bayar, bon, voucher, setoran, jumlah transaksi.
 *
 * Aturan hitung (retur tidak dihitung, barang pengganti dihitung) tetap dari
 * OutletLaporanService::sales() — tidak diubah supaya angkanya sama persis.
 */
class OutletPenjualanExport implements WithMultipleSheets
{
    private ?array $data = null;

    public function __construct(private readonly Request $request) {}

    protected function data(): array
    {
        return $this->data ??= app(OutletLaporanService::class)->sales($this->request);
    }

    public function fileName(): string
    {
        $d = $this->data();

        return Base::fileName('Penjualan-Outlet', $d['mulai'] ?? null, $d['selesai'] ?? null);
    }

    public function sheets(): array
    {
        $d = $this->data();
        $period = Base::periodLabel($d['mulai'] ?? null, $d['selesai'] ?? null);
        $filters = $this->filterTexts($d);

        $detail = new TableReportSheet(
            'Laporan Penjualan Outlet',
            [
                Base::col('No', 'no'),
                Base::col('Tanggal', 'datetime', 17),
                Base::col('Invoice', 'code', 20),
                Base::col('Outlet', 'text', 20),
                Base::col('Kasir', 'text', 18),
                Base::col('Jenis', 'center', 15),
                Base::col('Barcode', 'code', 17),
                Base::col('Produk', 'text', 36, ['wrap' => true]),
                Base::col('Qty', 'qty', 9, ['sum' => true]),
                Base::col('Harga Satuan', 'money', 15),
                Base::col('Rafaksi', 'money', 13),
                Base::col('Subtotal', 'money', 16, ['sum' => true]),
                Base::col('Payment Method', 'text', 18),
            ],
            fn () => collect($d['rows'] ?? [])->map(fn ($r) => [
                $r['tanggal'] ?? null,
                $r['invoice'] ?? null,
                $r['outlet'] ?? null,
                $r['kasir'] ?? null,
                $r['type'] ?? null,
                $r['barcode'] ?? null,
                $r['product'] ?? null,
                $r['qty'] ?? 0,
                $r['unit_price'] ?? 0,
                $r['rafaksi'] ?? 0,
                $r['subtotal'] ?? 0,
                $r['payment_method'] ?? null,
            ]),
            ['sheet' => 'Penjualan', 'period' => $period, 'filters' => $filters]
        );

        $summary = $d['summary'] ?? [];
        $recap = new TableReportSheet(
            'Rekap Penjualan Outlet',
            [
                Base::col('Uraian', 'text', 38),
                Base::col('Nilai', 'money', 22),
            ],
            function () use ($d, $summary) {
                $rows = [['Total Penjualan', $summary['total_penjualan'] ?? 0]];
                foreach (($d['paymentMethods'] ?? []) as $method => $amount) {
                    $rows[] = ['   '.$method, $amount];
                }
                $rows[] = ['Bon', $summary['bon'] ?? 0];
                $rows[] = ['Total Voucher', $summary['total_voucher'] ?? 0];
                $rows[] = ['Setoran Akhir', $summary['setoran_akhir'] ?? 0];
                $rows[] = ['Jumlah Transaksi', $summary['jumlah_transaksi'] ?? 0];

                return $rows;
            },
            ['sheet' => 'Rekap', 'period' => $period, 'filters' => $filters, 'logo' => false]
        );

        return [$detail, $recap];
    }

    /** @return string[] */
    private function filterTexts(array $d): array
    {
        $outlet = ! empty($d['outlet_id']) ? optional(Outlet::find($d['outlet_id']))->name : null;
        $kasir = ! empty($d['kasir_id']) ? optional(User::find($d['kasir_id']))->name : null;

        return [
            'Outlet: '.($outlet ?? 'Semua Outlet'),
            'Kasir: '.($kasir ?? 'Semua Kasir'),
        ];
    }
}