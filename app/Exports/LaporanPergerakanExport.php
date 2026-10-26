<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Product;
use App\Models\ProductMinimumAdjustment;
use App\Models\StockMovement;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanPergerakanExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Pergerakan-Stok';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Pergerakan dan Kebutuhan Stok';
    }

    /** Laporan ini berupa posisi stok saat ini, bukan rentang tanggal. */
    protected function periodText(): ?string
    {
        return 'Posisi per '.now()->format('d/m/Y');
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Stok', 'qty', 10),
            self::col('Konversi Stok', 'center', 18),
            self::col('Rata-rata Keluar/Bln', 'dec', 14),
            self::col('Hari Tanpa Trx', 'int', 12),
            self::col('Kategori', 'center', 16),
            self::col('Status Stok', 'center', 13),
            self::col('Min Stok', 'int', 10),
            self::col('Saran Reorder', 'center', 12),
            self::col('Qty Reorder', 'qty', 11),
            self::col('Konversi Reorder', 'center', 18),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $movementStats = StockMovement::selectRaw('product_id, SUM(qty_out) as total_out, MIN(created_at) as first_date, MAX(created_at) as last_date')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Per PRODUK dengan stok fisik = SUM(stocks.qty) semua batch (bukan per baris batch)
        $products = Product::with('category')
            ->withSum('stocks as stock_qty', 'qty')
            ->whereHas('stocks')
            ->orderBy('code')
            ->get();

        $activeAdjs = ProductMinimumAdjustment::activeOn(now()->toDateString())
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->keyBy('product_id');

        foreach ($products as $p) {
            $qty = (int) ($p->stock_qty ?? 0);
            $stat = $movementStats[$p->id] ?? null;
            $totalOut = (int) ($stat?->total_out ?? 0);
            $months = max(1, (int) Carbon::parse($stat?->first_date ?? now())->diffInMonths(now()) + 1);
            $avgKeluar = round($totalOut / $months, 1);
            $hariTanpa = $stat ? (int) now()->diffInDays(Carbon::parse($stat->last_date)) : 0;
            $baseMin = $p->min_stock ?? 0;
            $adj = $activeAdjs->get($p->id);
            $minStok = $adj ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100)) : (int) $baseMin;

            $kategori = $avgKeluar >= 10 ? 'Fast Moving' : ($avgKeluar >= 3 ? 'Medium Moving' : 'Slow Moving');
            $statusStok = $qty > $minStok ? 'Aman' : ($qty > 0 ? 'Kritis' : 'Habis');
            $perluReorder = $qty <= $minStok;
            $qtyReorder = $perluReorder ? max(0, $minStok * 2 - $qty) : 0;

            yield [
                $p->code ?? '-',
                $p->name ?? '-',
                $qty,
                $this->konversi($p, $qty),
                $avgKeluar,
                $hariTanpa,
                $kategori,
                $statusStok,
                $minStok,
                $perluReorder ? 'Ya' : 'Tidak',
                $qtyReorder,
                $qtyReorder > 0 ? $this->konversi($p, $qtyReorder) : null,
                null,
            ];
        }
    }

    protected function decorateData(Worksheet $sheet, array $columns, array $data, int $first, int $lastData, string $last): void
    {
        parent::decorateData($sheet, $columns, $data, $first, $lastData, $last);

        $this->colorByHeader($sheet, 'Status Stok', $first, $lastData, [
            'Aman' => 'FF00803C', 'Kritis' => 'FFC55A11', 'Habis' => 'FFC00000',
        ]);
        $this->colorByHeader($sheet, 'Saran Reorder', $first, $lastData, ['Ya' => 'FFC00000']);
    }

    protected function numberFormat(array $column, int $index): string
    {
        // Rata-rata keluar memang 1 desimal.
        return $column['header'] === 'Rata-rata Keluar/Bln' ? '#,##0.0;-#,##0.0;0.0' : parent::numberFormat($column, $index);
    }
}