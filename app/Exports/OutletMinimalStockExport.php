<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Outlet;
use App\Services\OutletLaporanService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan Minimal Stock Outlet — satu baris per outlet + produk (tanpa rowspan).
 * Rumus min stock tetap dari OutletLaporanService::minimumStock() (tidak diubah).
 */
class OutletMinimalStockExport extends RequestReportExport
{
    private ?array $data = null;

    private function data(): array
    {
        return $this->data ??= app(OutletLaporanService::class)->minimumStock($this->request);
    }

    protected function fileSlug(): string
    {
        return 'Outlet-Minimal-Stock';
    }

    public function downloadName(): string
    {
        return self::fileName($this->fileSlug());
    }

    protected function reportTitle(): string
    {
        return 'Laporan Minimal Stock Outlet';
    }

    protected function periodText(): ?string
    {
        $asOf = $this->data()['asOf'] ?? null;

        return 'Per Tanggal: '.($asOf ? Carbon::parse($asOf)->format('d/m/Y') : now()->format('d/m/Y'));
    }

    protected function filterTexts(): array
    {
        $d = $this->data();
        $id = $d['outlet_id'] ?? null;
        $s = $d['summary'] ?? [];

        return [
            'Outlet: '.($id ? (optional(Outlet::find($id))->name ?? '-') : 'Semua Outlet'),
            'Total Produk: '.($s['total_produk'] ?? 0),
            'Out of Stock: '.($s['out_of_stock'] ?? 0),
        ];
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Outlet', 'text', 22),
            self::col('Barcode', 'code', 17),
            self::col('Produk', 'text', 36, ['wrap' => true]),
            self::col('Kategori', 'text', 18),
            self::col('Periode Penjualan', 'center', 24),
            self::col('Penjualan Rata-rata/Bulan', 'dec', 15),
            self::col('Jumlah PO (3 Bulan)', 'int', 13),
            self::col('PO/Bulan', 'dec', 10),
            self::col('Faktor', 'dec', 9),
            self::col('Min Stock Manual', 'int', 12),
            self::col('Min Stock Hasil Hitung', 'int', 14),
            self::col('Stok Gudang Saat Ini', 'qty', 14),
            self::col('Saran Qty PO', 'qty', 12, ['sum' => true]),
            self::col('Status Stock', 'center', 14),
            self::col('Status Perhitungan', 'center', 22),
            self::col('Penjualan Pertama', 'date', 14),
            self::col('Per Tanggal', 'date', 13),
        ];
    }

    protected function rows(): iterable
    {
        $d = $this->data();
        $asOf = $d['asOf'] ?? null;

        foreach ($d['rows'] ?? [] as $r) {
            yield [
                $r['outlet'] ?? '-',
                $r['barcode'] ?? '-',
                $r['product'] ?? '-',
                $r['category'] ?? '-',
                Carbon::parse($r['window_start'])->format('d/m/Y').' s/d '.Carbon::parse($r['window_end'])->format('d/m/Y'),
                $r['average_sold'] ?? 0,
                $r['po_count'] ?? 0,
                $r['po_per_month'] ?? 0,
                $r['factor'] ?? '-',
                $r['manual_min_stock'] ?? 0,
                $r['min_stock'] ?? 0,
                $r['stock_qty'] ?? 0,
                $r['suggested_po_qty'] ?? 0,
                ($r['status'] ?? '') === 'out_of_stock' ? 'Out of Stock' : 'Normal',
                $r['calculation_status'] ?? '-',
                $r['first_sale'] ?? null,
                $asOf,
            ];
        }
    }

    protected function decorateData(Worksheet $sheet, array $columns, array $data, int $first, int $lastData, string $last): void
    {
        parent::decorateData($sheet, $columns, $data, $first, $lastData, $last);

        $this->colorByHeader($sheet, 'Status Stock', $first, $lastData, [
            'Out of Stock' => 'FFC00000', 'Normal' => 'FF00803C',
        ]);
    }
}