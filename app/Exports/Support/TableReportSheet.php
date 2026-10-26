<?php

namespace App\Exports\Support;

use Closure;

/**
 * Sheet laporan generik: cukup beri judul, kolom, dan sumber baris.
 * Dipakai oleh export yang tidak butuh class sendiri.
 *
 * Opsi ($options): sheet (nama tab), period (teks periode), filters (array teks),
 *                  logo (bool, default true).
 */
class TableReportSheet extends BaseReportExport
{
    /**
     * @param  array             $columns  hasil BaseReportExport::col()
     * @param  Closure|iterable  $rows     Closure dipanggil sekali saat data dibutuhkan
     */
    public function __construct(
        private readonly string $reportName,
        private readonly array $tableColumns,
        private readonly Closure|iterable $tableRows,
        private readonly array $options = [],
    ) {
        $this->withLogo = (bool) ($options['logo'] ?? true);
    }

    protected function reportTitle(): string
    {
        return $this->reportName;
    }

    protected function sheetName(): string
    {
        return $this->options['sheet'] ?? $this->reportName;
    }

    protected function columns(): array
    {
        return $this->tableColumns;
    }

    protected function rows(): iterable
    {
        return $this->tableRows instanceof Closure ? ($this->tableRows)() : $this->tableRows;
    }

    protected function periodText(): ?string
    {
        return $this->options['period'] ?? null;
    }

    protected function filterTexts(): array
    {
        return $this->options['filters'] ?? [];
    }
}