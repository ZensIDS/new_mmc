<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Stock;

class LaporanPenerimaanBarangExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Penerimaan-Barang';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Penerimaan Barang';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('No Dokumen', 'code', 20),
            self::col('No PO', 'code', 20),
            self::col('Supplier', 'text', 24),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Expired', 'date'),
            self::col('Qty', 'qty', 9),
            self::col('Konversi', 'center', 18),
            self::col('Qty Diterima', 'qty', 12),
            self::col('Konversi Diterima', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Kondisi', 'center', 12),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $stocks = Stock::with(['pembelian.supplier', 'product'])
            ->whereHas('pembelian')
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')
            ->get();

        foreach ($stocks as $s) {
            $qty = $s->qty ?? 0;
            $qtyDiterima = $s->qty_diterima ?? 0;

            yield [
                $s->created_at,
                $s->pembelian?->code_gr ?? ($s->pembelian?->code ?? '-'),
                $s->pembelian?->code ?? '-',
                $s->pembelian?->supplier?->name ?? '-',
                $s->product?->code ?? '-',
                $s->product?->name ?? '-',
                $s->sku ?? '-',
                $s->expired_at,
                $qty,
                $this->konversi($s->product, $qty),
                $qtyDiterima,
                $this->konversi($s->product, $qtyDiterima),
                $s->product?->satuan ?? 'PCS',
                'Baik',
                $s->pembelian?->notes ?? 'Pembelian',
            ];
        }
    }
}