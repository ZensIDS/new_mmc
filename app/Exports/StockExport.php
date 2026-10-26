<?php

namespace App\Exports;

use App\Exports\Support\BaseReportExport;
use App\Exports\Support\ReportHelpers;
use App\Models\ProductMinimumAdjustment;
use App\Models\Stock;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan Stok Gudang (satu baris per batch).
 *
 * Pemakaian di controller:
 *   $export = new StockExport;
 *   return Excel::download($export, $export->downloadName());
 */
class StockExport extends BaseReportExport
{
    use ReportHelpers;

    public function downloadName(): string
    {
        return self::fileName('Stok-Gudang');
    }

    protected function reportTitle(): string
    {
        return 'Laporan Stok Barang';
    }

    protected function periodText(): ?string
    {
        return 'Posisi per '.now()->format('d/m/Y H:i');
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Expired Date', 'date'),
            self::col('Kategori', 'text', 18),
            self::col('Satuan', 'center', 10),
            self::col('Stok Batch', 'qty', 11),
            self::col('Konversi Batch', 'center', 18),
            self::col('Total Stok Produk', 'qty', 13),
            self::col('Konversi Total', 'center', 18),
            self::col('Min Stok', 'int', 10),
            self::col('Selisih', 'int', 10),
            self::col('Status Stok', 'center', 13),
            self::col('Status Expired', 'center', 15),
            self::col('Lokasi', 'center', 12),
        ];
    }

    protected function rows(): iterable
    {
        $stocks = Stock::with(['product.category', 'pembelian'])
            ->orderBy('product_id')
            ->get();

        $activeAdjs = ProductMinimumAdjustment::activeOn(now()->toDateString())
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->keyBy('product_id');

        // Total stok fisik per produk = SUM(stocks.qty) semua batch (sama dengan menu Stok/Produk).
        // Min Stok, Selisih, dan Status Stok dinilai terhadap TOTAL ini, bukan qty satu batch.
        $productTotals = Stock::selectRaw('product_id, SUM(qty) as total_qty')
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        foreach ($stocks as $s) {
            $baseMin = $s->product?->min_stock ?? 0;
            $adj = $activeAdjs->get($s->product_id);
            $minStok = $adj
                ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100))
                : (int) $baseMin;
            $totalQty = (int) ($productTotals[$s->product_id] ?? 0);
            $statusStok = $totalQty > $minStok ? 'Aman' : ($totalQty > 0 ? 'Kritis' : 'Habis');
            $statusExp = $s->expired_at ? (Carbon::parse($s->expired_at)->isPast() ? 'Expired' : 'Belum Expired') : '-';
            $qty = $s->qty ?? 0;

            yield [
                $s->product?->code ?? '-',
                $s->product?->name ?? '-',
                $s->sku ?? '-',
                $s->expired_at,
                $s->product?->category?->name ?? '-',
                $s->product?->satuan ?? 'PCS',
                $qty,
                $this->konversi($s->product, $qty),
                $totalQty,
                $this->konversi($s->product, $totalQty),
                $minStok,
                $totalQty - $minStok,
                $statusStok,
                $statusExp,
                $s->location ?? '-',
            ];
        }
    }

    protected function decorateData(Worksheet $sheet, array $columns, array $data, int $first, int $lastData, string $last): void
    {
        parent::decorateData($sheet, $columns, $data, $first, $lastData, $last);

        $this->colorByHeader($sheet, 'Status Stok', $first, $lastData, [
            'Aman' => 'FF00803C', 'Kritis' => 'FFC55A11', 'Habis' => 'FFC00000',
        ]);
        $this->colorByHeader($sheet, 'Status Expired', $first, $lastData, ['Expired' => 'FFC00000']);
    }

    protected function numberFormat(array $column, int $index): string
    {
        // Selisih tampil dengan tanda +/-.
        return $column['header'] === 'Selisih' ? '+#,##0;-#,##0;0' : parent::numberFormat($column, $index);
    }
}