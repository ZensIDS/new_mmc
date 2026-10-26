<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\DeliveryOrder;
use App\Models\StockMovement;
use App\Support\ReportQuery;

class LaporanBarangKeluarExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Barang-Keluar';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Barang Keluar';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('No Dokumen', 'code', 20),
            self::col('Tujuan', 'text', 24),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Qty Keluar', 'qty', 11),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Keterangan', 'text', 30, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $movements = ReportQuery::betweenDates(
            StockMovement::with(['product'])->where('qty_out', '>', 0),
            'created_at', $mulai, $selesai
        )->orderBy('created_at')->get();

        $refs = ReportQuery::resolveReferences($movements, [
            DeliveryOrder::class => ['owner', 'requestOrder.owner'],
        ]);

        foreach ($movements as $m) {
            $ref = ReportQuery::ref($refs, $m);

            preg_match('/SKU:\s*(\S+)/', $m->notes ?? '', $matches);

            yield [
                $m->created_at,
                $ref?->code ?? '-',
                $ref?->owner?->name ?? $ref?->requestOrder?->owner?->name ?? '-',
                $m->product?->code ?? '-',
                $m->product?->name ?? '-',
                $matches[1] ?? '-',
                $m->qty_out ?? 0,
                $this->konversi($m->product, $m->qty_out ?? 0),
                $m->product?->satuan ?? 'PCS',
                $m->notes ?? '',
            ];
        }
    }
}