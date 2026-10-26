<?php

namespace App\Exports\Support;

use Illuminate\Http\Request;

/**
 * Dasar export laporan yang filternya berasal dari Request
 * (tanggal_mulai / tanggal_selesai). Turunan cukup mengisi:
 *   fileSlug(), reportTitle(), columns(), rows().
 *
 * Pemakaian di controller:
 *   $export = new LaporanPOExport($request);
 *   return Excel::download($export, $export->downloadName());
 */
abstract class RequestReportExport extends BaseReportExport
{
    use ReportHelpers;

    public function __construct(protected readonly Request $request) {}

    /** Potongan nama file, mis. "PO" -> Laporan-PO_20261001-20261026.xlsx */
    abstract protected function fileSlug(): string;

    /** Nama file unduhan (BaseReportExport::fileName() adalah static, jadi memakai nama lain). */
    public function downloadName(): string
    {
        return self::fileName($this->fileSlug(), $this->request->input('tanggal_mulai'), $this->request->input('tanggal_selesai'));
    }

    protected function periodText(): ?string
    {
        return $this->requestPeriod($this->request);
    }
}