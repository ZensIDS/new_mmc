<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\StockMovement;
use App\Support\ReportQuery;

class LaporanAktifitasExport extends RequestReportExport
{
    protected function fileSlug(): string
    {
        return 'Aktivitas-Gudang';
    }

    protected function reportTitle(): string
    {
        return 'Laporan Aktivitas Gudang';
    }

    protected function columns(): array
    {
        return [
            self::col('No', 'no'),
            self::col('Tanggal', 'date'),
            self::col('Jenis Aktivitas', 'center', 16),
            self::col('No Dokumen', 'code', 20),
            self::col('Kode Barang', 'code', 16),
            self::col('Nama Barang', 'text', 36, ['wrap' => true]),
            self::col('Qty', 'qty', 9),
            self::col('Konversi', 'center', 18),
            self::col('Satuan', 'center', 10),
            self::col('Lokasi', 'center', 12),
            self::col('PIC', 'text', 20),
            self::col('Status', 'center', 16),
            self::col('Keterangan', 'text', 30, ['wrap' => true]),
        ];
    }

    protected function rows(): iterable
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        // product.suppliers di-eager-load (sebelumnya lazy-load per baris)
        $movements = ReportQuery::betweenDates(
            StockMovement::with(['product.suppliers']),
            'created_at', $mulai, $selesai
        )->orderBy('created_at')->get();

        $refs = ReportQuery::resolveReferences($movements);

        foreach ($movements as $m) {
            $qty = max($m->qty_in ?? 0, $m->qty_out ?? 0);

            yield [
                $m->created_at,
                $m->qty_in > 0 ? 'Penerimaan' : 'Pengiriman',
                ReportQuery::ref($refs, $m)?->code ?? '-',
                $m->product?->code ?? '-',
                $m->product?->name ?? '-',
                $qty,
                $this->konversi($m->product, $qty),
                $m->product?->satuan ?? 'PCS',
                $m->product?->lokasi,
                optional($m->product?->suppliers)->pluck('pic_supplier')?->filter()->implode(', '),
                $m->type,
                $m->notes ?? '',
            ];
        }
    }
}