<?php

namespace App\Exports;

use App\Exports\Support\BaseReportExport;
use App\Exports\Support\ReportHelpers;
use App\Models\RefundPembelian;

/**
 * Laporan Retur Outlet ke gudang (satu baris per barang retur).
 * Parameter $settings dipertahankan agar pemanggilan lama tetap jalan;
 * identitas header kini diambil dari ReportIdentity (menu Setting).
 */
class ReturOutletExport extends BaseReportExport
{
    use ReportHelpers;

    public function __construct(
        protected $mulai,
        protected $selesai,
        protected array $settings = [],
    ) {}

    public function downloadName(): string
    {
        return self::fileName('Retur-Outlet', $this->mulai, $this->selesai);
    }

    protected function reportTitle(): string
    {
        return 'Laporan Retur Outlet';
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
            self::col('Jenis Retur', 'center', 12),
            self::col('Outlet', 'text', 22),
            self::col('Kode Request', 'code', 20),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Expired', 'date'),
            self::col('Qty', 'qty', 9),
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
            'outlet',
            'deliveryOrder',
            'refundPembelianItems.product',
            'refundPembelianItems.stock',
        ])
            ->where('type', 'outlet_ke_gudang')
            ->whereDate('tanggal', '>=', $this->mulai)
            ->whereDate('tanggal', '<=', $this->selesai)
            ->orderBy('tanggal')
            ->get();

        foreach ($returs as $retur) {
            foreach ($retur->refundPembelianItems as $item) {
                yield [
                    $retur->tanggal,
                    $retur->code,
                    'IN',
                    $retur->outlet?->name ?? '-',
                    $retur->deliveryOrder?->code ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?? '-',
                    $item->stock?->expired_at,
                    $item->qty,
                    $this->konversi($item->product, $item->qty),
                    $item->product?->satuan ?? 'PCS',
                    $item->alasan,
                    $retur->status === 'complete' ? 'Terkirim' : 'Proses',
                    null,
                ];
            }
        }
    }
}