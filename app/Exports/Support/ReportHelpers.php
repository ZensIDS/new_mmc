<?php

namespace App\Exports\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Helper kecil yang dipakai bersama oleh export laporan (turunan BaseReportExport).
 */
trait ReportHelpers
{
    /** Teks konversi satuan besar (mis. "2 DUS 5 PCS"), null bila produk tidak punya konversi. */
    protected function konversi($product, $qty): ?string
    {
        if (! $product) {
            return null;
        }

        $k = $product->konversiDisplay((float) ($qty ?? 0));

        return ($k !== null && $k !== '' && $k !== '-') ? (string) $k : null;
    }

    /** "Periode: 01/10/2026 s.d. 26/10/2026" dari parameter request (tanggal_mulai / tanggal_selesai). */
    protected function requestPeriod(Request $request): string
    {
        $mulai = $request->input('tanggal_mulai');
        $selesai = $request->input('tanggal_selesai');

        if ($mulai && $selesai) {
            return BaseReportExport::periodLabel($mulai, $selesai);
        }
        if ($mulai) {
            return 'Periode: mulai '.Carbon::parse($mulai)->format('d/m/Y');
        }
        if ($selesai) {
            return 'Periode: sampai '.Carbon::parse($selesai)->format('d/m/Y');
        }

        return 'Periode: Semua Tanggal';
    }

    /**
     * Warnai teks kolom (berdasarkan judul header) sesuai isi selnya — lewat conditional
     * formatting (ringan untuk data besar). Hanya mengubah warna font supaya tidak
     * bentrok dengan warna zebra.
     *
     * @param  array<string,string>  $map  ['Kritis' => 'FFC00000', ...]
     */
    protected function colorByHeader(Worksheet $sheet, string $header, int $first, int $lastData, array $map): void
    {
        foreach ($this->columnList() as $i => $column) {
            if ($column['header'] !== $header) {
                continue;
            }

            $L = Coordinate::stringFromColumnIndex($i + 1);
            $styles = [];

            foreach ($map as $text => $argb) {
                $rule = new Conditional();
                $rule->setConditionType(Conditional::CONDITION_EXPRESSION);
                $rule->addCondition('$'.$L.$first.'="'.str_replace('"', '""', $text).'"');
                $rule->getStyle()->getFont()->setBold(true);
                $rule->getStyle()->getFont()->getColor()->setARGB($argb);
                $styles[] = $rule;
            }

            $sheet->getStyle("{$L}{$first}:{$L}{$lastData}")->setConditionalStyles($styles);

            return;
        }
    }
}