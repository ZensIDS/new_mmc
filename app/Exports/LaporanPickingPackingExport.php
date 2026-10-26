<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\PickingList;

class LaporanPickingPackingExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Picking-Packing';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Picking & Packing';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('Kode Picking', 'code', 20),
            self::col('Kode DO', 'code', 20),
            self::col('Tujuan', 'text', 22),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Lokasi', 'center', 12),
            self::col('Qty Order', 'qty', 11),
            self::col('Konversi Order', 'center', 18),
            self::col('Qty Pick', 'qty', 10),
            self::col('Konversi Pick', 'center', 18),
            self::col('Qty Pack', 'qty', 10),
            self::col('Status', 'center', 14),
            self::col('Picker', 'text', 16),
            self::col('Packer', 'text', 16),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $pickings = PickingList::with(['requestOrder.owner', 'items.product', 'deliveryOrder'])
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')
            ->get();

        foreach ($pickings as $pk) {
            foreach ($pk->items as $item) {
                yield [
                    $pk->created_at,
                    $pk->code,
                    $pk->deliveryOrder?->code ?? '-',
                    $pk->requestOrder?->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->location ?? '-',
                    $item->qty_to_pick,
                    $this->konversi($item->product, $item->qty_to_pick),
                    $item->qty_picked,
                    $this->konversi($item->product, $item->qty_picked),
                    $item->qty_picked,
                    ucfirst($pk->status ?? '-'),
                    $pk->picker?->name ?? '-',
                    '-',
                    $pk->notes ?? '',
                ];
            }
        }
    }
}