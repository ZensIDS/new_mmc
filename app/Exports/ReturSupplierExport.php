<?php

namespace App\Exports;

use App\Exports\Support\BaseReportExport;
use App\Exports\Support\ReportHelpers;
use App\Models\RefundPembelian;

/**
 * Laporan Retur ke Supplier (satu baris per barang retur).
 * Parameter $settings dipertahankan agar pemanggilan lama tetap jalan;
 * identitas header kini diambil dari ReportIdentity (menu Setting).
 */
class ReturSupplierExport extends BaseReportExport
{
    use ReportHelpers;

    public function __construct(
        protected $mulai,
        protected $selesai,
        protected array $settings = [],
    ) {}

    public function downloadName(): string
    {
        return self::fileName('Retur-Supplier', $this->mulai, $this->selesai);
    }

    protected function reportTitle(): string
    {
        return 'Laporan Retur ke Supplier';
    }

    protected function periodText(): ?string
    {
        return self::periodLabel($this->mulai, $this->selesai);
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('Kode Retur', 'code', 20),
            self::col('Kode PO', 'code', 20),
            self::col('Supplier', 'text', 24),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Qty Retur', 'qty', 10),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Alasan Retur', 'text', 26, ['wrap' => true]),
            self::col('Status', 'center', 12),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $returs = RefundPembelian::with([
            'supplier',
            'refundPembelianItems.product',
            'refundPembelianItems.stock.pembelian',
        ])
            ->where('type', 'gudang_ke_supplier')
            ->whereDate('tanggal', '>=', $this->mulai)
            ->whereDate('tanggal', '<=', $this->selesai)
            ->orderBy('tanggal')
            ->get();

        foreach ($returs as $retur) {
            foreach ($retur->refundPembelianItems as $item) {
                yield [
                    $retur->tanggal,
                    $retur->code,
                    $item->stock?->pembelian?->code ?? '-',
                    $retur->supplier?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?? '-',
                    $item->qty,
                    $this->konversi($item->product, $item->qty),
                    $item->product?->satuan ?? 'PCS',
                    $item->alasan,
                    $retur->status === 'retur' ? 'Proses' : 'Complete',
                    null,
                ];
            }
        }
    }
}