<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Outlet;
use App\Services\OutletLaporanService;

/**
 * Laporan Rafaksi Outlet — satu baris per produk, TOTAL berumus SUBTOTAL.
 * Perhitungan (retur mengurangi qty & diskon) tetap dari OutletLaporanService::rafaksi().
 */
class OutletRafaksiExport extends RequestReportExport
{
    private ?array $data = null;

    private function data(): array
    {
        return $this->data ??= app(OutletLaporanService::class)->rafaksi($this->request);
    }

    protected function fileSlug(): string
    {
        return 'Outlet-Rafaksi';
    }

    public function downloadName(): string
    {
        $d = $this->data();

        return self::fileName($this->fileSlug(), $d['mulai'] ?? null, $d['selesai'] ?? null);
    }

    protected function reportTitle(): string
    {
        return 'Laporan Rafaksi Outlet';
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
        $promoNames = collect($d['promotions'] ?? [])->pluck('name')->filter()->implode(', ');

        return array_filter([
            'Outlet: '.($id ? (optional(Outlet::find($id))->name ?? '-') : 'Semua Outlet'),
            ! empty($d['promotion_id']) && $promoNames !== '' ? 'Promosi: '.$promoNames : null,
        ]);
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Barcode', 'code', 17),
            self::col('Nama Product', 'text', 38, ['wrap' => true]),
            self::col('Qty Product Terjual', 'qty', 14, ['sum' => true]),
            self::col('Harga Akhir/Unit', 'money', 16),
            self::col('Diskon Rafaksi Product/Unit', 'money', 18),
            self::col('Total Diskon', 'money', 16, ['sum' => true]),
            self::col('Total Penjualan', 'money', 17, ['sum' => true]),
            self::col('Keterangan Rafaksi', 'text', 30, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        foreach ($this->data()['rows'] ?? [] as $r) {
            yield [
                $r['barcode'] ?? '-',
                $r['product'] ?? '-',
                $r['qty'] ?? 0,
                $r['unit_price'] ?? 0,
                $r['discount_per_unit'] ?? 0,
                $r['discount_total'] ?? 0,
                $r['subtotal'] ?? 0,
                $r['keterangan'] ?? '',
            ];
        }
    }
}