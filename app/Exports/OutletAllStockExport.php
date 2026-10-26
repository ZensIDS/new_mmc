<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Outlet;
use App\Services\OutletLaporanService;

/**
 * Laporan All Stock Outlet — satu baris per outlet + produk (tanpa rowspan,
 * supaya bisa difilter/pivot di Excel). TOTAL persediaan berumus SUBTOTAL.
 */
class OutletAllStockExport extends RequestReportExport
{
    private ?array $data = null;

    private function data(): array
    {
        return $this->data ??= app(OutletLaporanService::class)->allStock($this->request);
    }

    protected function fileSlug(): string
    {
        return 'Outlet-All-Stock';
    }

    public function downloadName(): string
    {
        return self::fileName($this->fileSlug());
    }

    protected function reportTitle(): string
    {
        return 'Laporan All Stock Outlet';
    }

    protected function periodText(): ?string
    {
        return 'Posisi per '.now()->format('d/m/Y H:i');
    }

    protected function filterTexts(): array
    {
        $id = $this->data()['outlet_id'] ?? null;

        return ['Outlet: '.($id ? (optional(Outlet::find($id))->name ?? '-') : 'Semua Outlet')];
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Outlet', 'text', 22),
            self::col('Barcode', 'code', 17),
            self::col('Produk', 'text', 36, ['wrap' => true]),
            self::col('Kategori', 'text', 18),
            self::col('Qty', 'qty', 10),
            self::col('Satuan', 'center', 10),
            self::col('HPP', 'money', 15),
            self::col('Pajak', 'money', 13),
            self::col('HPP + Pajak', 'money', 15),
            self::col('Persediaan Sebelum Pajak', 'money', 20, ['sum' => true]),
            self::col('Persediaan Setelah Pajak', 'money', 20, ['sum' => true]),
        ];
    }

    protected function rows(): iterable
    {
        foreach ($this->data()['rows'] ?? [] as $r) {
            yield [
                $r['outlet'] ?? '-',
                $r['barcode'] ?? '-',
                $r['product'] ?? '-',
                $r['category'] ?? '-',
                $r['qty'] ?? 0,
                $r['satuan'] ?? 'PCS',
                $r['hpp'] ?? 0,
                $r['tax'] ?? 0,
                $r['hpp_after_tax'] ?? 0,
                $r['inventory_before_tax'] ?? 0,
                $r['inventory_after_tax'] ?? 0,
            ];
        }
    }
}