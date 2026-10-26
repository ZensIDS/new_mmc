<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\RequestOrder;

class LaporanPRExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'PR';
    }

    protected function reportTitle(): string
    {
        return 'Laporan PR';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal PR', 'date'),
            self::col('Kode PR', 'code', 20),
            self::col('Outlet', 'text', 22),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('QTY', 'qty', 9),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Status', 'center', 14),
            self::col('Kode PO', 'code', 18),
            self::col('Keterangan', 'text', 24, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $orders = RequestOrder::with(['owner', 'items.product'])
            ->when($mulai, fn ($q) => $q->whereDate('request_date', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('request_date', '<=', $selesai))
            ->orderBy('request_date')->get();

        foreach ($orders as $ro) {
            foreach ($ro->items as $item) {
                yield [
                    $ro->request_date,
                    $ro->code,
                    $ro->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->qty_requested,
                    $this->konversi($item->product, $item->qty_requested),
                    $item->product?->satuan ?? 'PCS',
                    ucfirst($ro->status ?? '-'),
                    '-',
                    null,
                ];
            }
        }
    }
}