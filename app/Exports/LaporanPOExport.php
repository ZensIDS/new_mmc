<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Pembelian;

class LaporanPOExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'PO';
    }

    protected function reportTitle(): string
    {
        return 'Laporan PO';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal PO', 'date'),
            self::col('Kode PO', 'code', 20),
            self::col('No PR', 'code', 20),
            self::col('Supplier', 'text', 24),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('QTY', 'qty', 9),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Harga Total', 'money', 16, ['sum' => true]),
            self::col('QTY Diterima', 'qty', 12),
            self::col('Konversi Diterima', 'center', 18),
            self::col('Status', 'center', 14),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')->get();

        foreach ($pembelians as $p) {
            foreach ($p->pembelianProducts as $pp) {
                $qtyRcv = $pp->qty_received ?? $pp->qty;

                yield [
                    $p->created_at,
                    $p->code,
                    $p->requestOrder?->code ?? '-',
                    $p->supplier?->name ?? '-',
                    $pp->product?->code ?? '-',
                    $pp->product?->name ?? '-',
                    $pp->qty,
                    $this->konversi($pp->product, $pp->qty),
                    $pp->product?->satuan ?? 'PCS',
                    $pp->subtotal,
                    $qtyRcv,
                    $this->konversi($pp->product, $qtyRcv),
                    ucfirst($p->status ?? '-'),
                    null,
                ];
            }
        }
    }
}