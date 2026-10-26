<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\DeliveryOrder;

class LaporanPengirimanExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Pengiriman-Barang';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Pengiriman Barang';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('No Surat Jalan', 'code', 20),
            self::col('No Dokumen', 'code', 20),
            self::col('Tujuan', 'text', 22),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Qty', 'qty', 9),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Status', 'center', 14),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $deliveries = DeliveryOrder::with(['owner', 'requestOrder', 'items.product'])
            ->when($mulai, fn ($q) => $q->whereDate('delivery_date', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('delivery_date', '<=', $selesai))
            ->orderBy('delivery_date')
            ->get();

        foreach ($deliveries as $do) {
            foreach ($do->items as $item) {
                yield [
                    $do->delivery_date,
                    $do->code,
                    $do->requestOrder?->code ?? '-',
                    $do->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?? '-',
                    $item->qty,
                    $this->konversi($item->product, $item->qty),
                    $item->product?->satuan ?? 'PCS',
                    ucfirst($do->status ?? '-'),
                    null,
                ];
            }
        }
    }
}