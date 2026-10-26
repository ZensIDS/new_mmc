<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\Pembelian;
use App\Models\StockMovement;
use App\Support\ReportQuery;

class LaporanBarangMasukExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Barang-Masuk';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Barang Masuk';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('No Dokumen', 'code', 20),
            self::col('Supplier', 'text', 24),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Batch', 'code', 18),
            self::col('Qty Masuk', 'qty', 11),
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
            StockMovement::with(['product'])->where('qty_in', '>', 0),
            'created_at', $mulai, $selesai
        )->orderBy('created_at')->get();

        $refs = ReportQuery::resolveReferences($movements, [
            Pembelian::class => ['supplier'],
        ]);

        foreach ($movements as $m) {
            $ref = ReportQuery::ref($refs, $m);
            $supplier = $m->reference_type === Pembelian::class ? ($ref?->supplier?->name ?? '-') : '-';

            preg_match('/SKU:\s*(\S+)/', $m->notes ?? '', $matches);

            yield [
                $m->created_at,
                $ref?->code ?? '-',
                $supplier,
                $m->product?->code ?? '-',
                $m->product?->name ?? '-',
                $matches[1] ?? '-',
                $m->qty_in ?? 0,
                $this->konversi($m->product, $m->qty_in ?? 0),
                $m->product?->satuan ?? 'PCS',
                $m->notes ?? '',
            ];
        }
    }
}