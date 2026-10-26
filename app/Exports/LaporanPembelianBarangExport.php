<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Pembelian;

class LaporanPembelianBarangExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Pembelian-Barang';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Pembelian Barang';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('Kode PO', 'code', 20),
            self::col('Supplier', 'text', 24),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Qty', 'qty', 9),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Harga Satuan', 'money', 16),
            self::col('Total Harga', 'money', 16, ['sum' => true]),
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
            ->orderBy('created_at')
            ->get();

        foreach ($pembelians as $p) {
            foreach ($p->pembelianProducts as $pp) {
                yield [
                    $p->created_at,
                    $p->code,
                    $p->supplier?->name ?? '-',
                    $pp->product?->code ?? '-',
                    $pp->product?->name ?? '-',
                    $pp->qty ?? 0,
                    $this->konversi($pp->product, $pp->qty ?? 0),
                    $pp->product?->satuan ?? 'PCS',
                    $pp->harga_beli,
                    $pp->subtotal,
                    ucfirst($p->status ?? '-'),
                    null,
                ];
            }
        }
    }
}