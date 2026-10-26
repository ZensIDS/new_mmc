<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Outlet;
use App\Services\OutletLaporanService;

/**
 * Laporan Retur Penjualan Outlet — satu baris per pasangan barang retur/pengganti.
 */
class OutletReturExport extends RequestReportExport
{
    private ?array $data = null;

    private function data(): array
    {
        return $this->data ??= app(OutletLaporanService::class)->returns($this->request);
    }

    protected function fileSlug(): string
    {
        return 'Outlet-Retur';
    }

    public function downloadName(): string
    {
        $d = $this->data();

        return self::fileName($this->fileSlug(), $d['mulai'] ?? null, $d['selesai'] ?? null);
    }

    protected function reportTitle(): string
    {
        return 'Laporan Retur Penjualan Outlet';
    }

    protected function periodText(): ?string
    {
        $d = $this->data();

        return self::periodLabel($d['mulai'] ?? null, $d['selesai'] ?? null);
    }

    protected function filterTexts(): array
    {
        $d = $this->data();
        $id = $d['outlet_id'] ?? null;

        return [
            'Outlet: '.($id ? (optional(Outlet::find($id))->name ?? '-') : 'Semua Outlet'),
            'Jumlah Transaksi Retur: '.($d['summary']['jumlah_retur'] ?? 0),
        ];
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'datetime', 17),
            self::col('Kode Retur', 'code', 20),
            self::col('Invoice', 'code', 20),
            self::col('Outlet', 'text', 20),
            self::col('Petugas', 'text', 18),
            self::col('Barcode Barang Retur', 'code', 18),
            self::col('Barang Retur', 'text', 32, ['wrap' => true]),
            self::col('Qty Retur', 'qty', 10, ['sum' => true]),
            self::col('Barcode Pengganti', 'code', 18),
            self::col('Barang Pengganti', 'text', 32, ['wrap' => true]),
            self::col('Qty Pengganti', 'qty', 12, ['sum' => true]),
            self::col('Selisih', 'money', 15, ['sum' => true]),
            self::col('Keterangan', 'text', 28, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        foreach ($this->data()['rows'] ?? [] as $r) {
            yield [
                $r['tanggal'] ?? null,
                $r['kode_retur'] ?? '-',
                $r['invoice'] ?? '-',
                $r['outlet'] ?? '-',
                $r['petugas'] ?? '-',
                $r['barcode_retur'] ?? '-',
                $r['barang_retur'] ?? '-',
                $r['qty_retur'] ?? 0,
                $r['barcode_pengganti'] ?? '-',
                $r['barang_pengganti'] ?? '-',
                $r['qty_pengganti'] ?? 0,
                $r['selisih'] ?? 0,
                $r['keterangan'] ?? '',
            ];
        }
    }
}