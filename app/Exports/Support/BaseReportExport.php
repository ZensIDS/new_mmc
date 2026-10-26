<?php

namespace App\Exports\Support;

use App\Support\ReportIdentity;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kerangka standar semua Excel laporan (satu sheet).
 *
 * Susunan sheet:
 *   Baris 1-3  : logo + nama perusahaan, alamat, kontak (dari menu Setting)
 *   Baris 6-9  : judul laporan, periode, filter aktif, dicetak oleh & waktu
 *   Baris 11   : header tabel (HEADER_ROW) -> data mulai baris 12
 *   Setelah data: baris TOTAL berumus SUBTOTAL (tetap benar saat difilter)
 *
 * Angka & tanggal ditulis sebagai nilai Excel asli (bukan teks), sehingga bisa
 * di-SUM, difilter, dan diurutkan. Kolom teks yang tampak seperti angka/rumus
 * (barcode, kode) dipaksa tetap teks.
 *
 * Turunan cukup mengisi reportTitle(), columns(), rows().
 */
abstract class BaseReportExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithEvents, WithDrawings, WithProperties
{
    public const HEADER_ROW = 11;

    protected const COLOR_HEADER = 'FF4472C4';
    protected const COLOR_HEADER_LINE = 'FF2F5597';
    protected const COLOR_BORDER = 'FFBFBFBF';
    protected const COLOR_TOTAL = 'FFD9E2F3';
    protected const COLOR_ZEBRA = 'FFF5F8FD';

    /** Tampilkan logo di pojok kiri atas. */
    protected bool $withLogo = true;

    private ?array $data = null;
    private ?array $columnCache = null;
    private ?array $identity = null;

    // ── Yang diisi turunan ────────────────────────────────────────────────

    abstract protected function reportTitle(): string;

    /** Daftar kolom; buat dengan self::col(). */
    abstract protected function columns(): array;

    /** Baris data (tanpa kolom 'no'): setiap baris array nilai sesuai urutan kolom. */
    abstract protected function rows(): iterable;

    protected function sheetName(): string
    {
        return $this->reportTitle();
    }

    protected function periodText(): ?string
    {
        return null;
    }

    /** @return string[] contoh: ['Outlet: Semua Outlet', 'Kasir: Budi'] */
    protected function filterTexts(): array
    {
        return [];
    }

    // ── Helper publik ─────────────────────────────────────────────────────

    /**
     * Definisi kolom.
     * Tipe: no (nomor otomatis), text, code (barcode/kode, selalu teks), center,
     *       int, qty, dec, money, date, datetime.
     * Opsi: sum => bool (baris TOTAL), wrap => bool (bungkus teks).
     */
    public static function col(string $header, string $type = 'text', ?int $width = null, array $opts = []): array
    {
        $defaults = [
            'no' => 6, 'text' => 24, 'code' => 18, 'center' => 14, 'int' => 10,
            'qty' => 10, 'dec' => 14, 'money' => 16, 'date' => 12, 'datetime' => 17,
        ];

        return [
            'header' => $header,
            'type'   => $type,
            'width'  => $width ?? ($defaults[$type] ?? 20),
            'sum'    => (bool) ($opts['sum'] ?? false),
            'wrap'   => (bool) ($opts['wrap'] ?? false),
        ];
    }

    /** Nama file standar: Laporan-Penjualan-Outlet_20261001-20261026.xlsx */
    public static function fileName(string $slug, $mulai = null, $selesai = null): string
    {
        $name = 'Laporan-'.trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', $slug), '-');

        if ($mulai && $selesai) {
            $name .= '_'.Carbon::parse($mulai)->format('Ymd').'-'.Carbon::parse($selesai)->format('Ymd');
        } else {
            $name .= '_'.now()->format('Ymd');
        }

        return $name.'.xlsx';
    }

    /** "01/10/2026 s.d. 26/10/2026" */
    public static function periodLabel($mulai, $selesai): ?string
    {
        if (! $mulai || ! $selesai) {
            return null;
        }

        return 'Periode: '.Carbon::parse($mulai)->format('d/m/Y').' s.d. '.Carbon::parse($selesai)->format('d/m/Y');
    }

    // ── Kontrak Maatwebsite ───────────────────────────────────────────────

    public function title(): string
    {
        $name = trim(preg_replace('/[\\\\\/\?\*\[\]:]+/', ' ', $this->sheetName()));

        return Str::limit($name !== '' ? $name : 'Laporan', 31, '');
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    public function headings(): array
    {
        return array_map(fn ($c) => $c['header'], $this->columnList());
    }

    public function collection()
    {
        return collect($this->prepared());
    }

    public function properties(): array
    {
        $identity = $this->identity();

        return [
            'creator'     => $identity['name'],
            'company'     => $identity['name'],
            'title'       => $this->reportTitle(),
            'description' => $this->reportTitle(),
        ];
    }

    public function drawings()
    {
        $logo = $this->identity()['logo'];

        if (! $this->withLogo || ! $logo || ! is_file($logo)) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($logo);
        $drawing->setHeight(52);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(2);

        return $drawing;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->decorate($event->sheet->getDelegate());
            },
        ];
    }

    // ── Data ──────────────────────────────────────────────────────────────

    protected function columnList(): array
    {
        return $this->columnCache ??= array_values($this->columns());
    }

    protected function identity(): array
    {
        return $this->identity ??= ReportIdentity::get();
    }

    /** Baris siap tulis: nomor otomatis, tanggal -> serial Excel, angka -> numerik. */
    protected function prepared(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $columns = $this->columnList();
        $out = [];
        $no = 0;

        foreach ($this->rows() as $row) {
            $row = array_values((array) $row);
            $line = [];
            $i = 0;
            $no++;

            foreach ($columns as $column) {
                if ($column['type'] === 'no') {
                    $line[] = $no;
                    continue;
                }

                $line[] = $this->convert($row[$i] ?? null, $column['type']);
                $i++;
            }

            $out[] = $line;
        }

        return $this->data = $out;
    }

    protected function convert($value, string $type)
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($type) {
            case 'date':
            case 'datetime':
                try {
                    if (is_string($value)) {
                        $value = Carbon::parse($value);
                    }
                    if ($value instanceof DateTimeInterface) {
                        return ExcelDate::dateTimeToExcel($value);
                    }
                } catch (\Throwable $e) {
                    return is_scalar($value) ? (string) $value : null;
                }

                return is_scalar($value) ? (string) $value : null;

            case 'int':
            case 'qty':
            case 'dec':
            case 'money':
                return is_numeric($value) ? $value + 0 : (is_scalar($value) ? (string) $value : null);

            default:
                if (is_bool($value)) {
                    return $value ? 'Ya' : 'Tidak';
                }

                return is_scalar($value) ? (string) $value : null;
        }
    }

    // ── Tampilan ──────────────────────────────────────────────────────────

    protected function numberFormat(array $column, int $index): string
    {
        switch ($column['type']) {
            case 'money':
            case 'int':
                return '#,##0;-#,##0;0';
            case 'dec':
                return '#,##0.00;-#,##0.00;0.00';
            case 'qty':
                // Desimal hanya ditampilkan bila datanya memang berdesimal.
                foreach ($this->prepared() as $row) {
                    $v = $row[$index] ?? null;
                    if (is_numeric($v) && floor((float) $v) != (float) $v) {
                        return '#,##0.00;-#,##0.00;0';
                    }
                }

                return '#,##0;-#,##0;0';
            case 'date':
                return 'dd/mm/yyyy';
            case 'datetime':
                return 'dd/mm/yyyy hh:mm';
            case 'text':
            case 'code':
                return '@';
            default:
                return 'General';
        }
    }

    protected function decorate(Worksheet $sheet): void
    {
        $columns = $this->columnList();
        $n = max(1, count($columns));
        $last = Coordinate::stringFromColumnIndex($n);
        $H = self::HEADER_ROW;
        $identity = $this->identity();
        $data = $this->prepared();
        $count = count($data);
        $first = $H + 1;
        $lastData = $H + $count;

        $sheet->setShowGridlines(false);
        $sheet->getSheetView()->setZoomScale(90);

        // Lebar kolom
        foreach ($columns as $i => $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($column['width']);
        }

        // ── Blok identitas ──
        $logoShown = $this->withLogo && $identity['logo'] && is_file($identity['logo']);
        $textFrom = ($logoShown && $n >= 5) ? 'C' : 'A';
        $textAlign = $textFrom === 'C' ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER;

        $line = function (int $row, ?string $text, string $from, array $font, string $align) use ($sheet, $last) {
            if ($text === null || $text === '') {
                return;
            }
            $sheet->setCellValueExplicit($from.$row, $text, DataType::TYPE_STRING);
            if ($from !== $last) {
                $sheet->mergeCells("{$from}{$row}:{$last}{$row}");
            }
            $sheet->getStyle("{$from}{$row}")->applyFromArray([
                'font'      => $font,
                'alignment' => ['horizontal' => $align, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        };

        $line(1, $identity['name'], $textFrom, ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF1F3864']], $textAlign);
        $line(2, $identity['address'], $textFrom, ['size' => 10, 'color' => ['argb' => 'FF404040']], $textAlign);
        $line(3, $identity['contact'], $textFrom, ['size' => 10, 'color' => ['argb' => 'FF404040']], $textAlign);

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(16);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(5);
        $sheet->getRowDimension(5)->setRowHeight(6);
        $sheet->getRowDimension(10)->setRowHeight(6);

        $sheet->getStyle("A4:{$last}4")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        // ── Judul, periode, filter, pencetak ──
        $center = Alignment::HORIZONTAL_CENTER;
        $line(6, mb_strtoupper($this->reportTitle()), 'A', ['bold' => true, 'size' => 14], $center);
        $line(7, $this->periodText(), 'A', ['size' => 11], $center);
        $line(8, implode('   |   ', array_filter($this->filterTexts())), 'A', ['size' => 10, 'color' => ['argb' => 'FF404040']], $center);
        $printedBy = optional(auth()->user())->name ?? '-';
        $line(9, 'Dicetak oleh '.$printedBy.' pada '.now()->format('d/m/Y H:i'), 'A', ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF7F7F7F']], $center);
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ── Header tabel ──
        $sheet->getStyle("A{$H}:{$last}{$H}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_HEADER_LINE]]],
        ]);
        $sheet->getRowDimension($H)->setRowHeight(30);

        $printEnd = $H;

        if ($count === 0) {
            $sheet->setCellValueExplicit('A'.$first, 'Tidak ada data pada periode / filter yang dipilih.', DataType::TYPE_STRING);
            if ($last !== 'A') {
                $sheet->mergeCells("A{$first}:{$last}{$first}");
            }
            $sheet->getStyle("A{$first}:{$last}{$first}")->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['argb' => 'FF7F7F7F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
            ]);
            $sheet->getRowDimension($first)->setRowHeight(26);
            $printEnd = $first;
        } else {
            $this->decorateData($sheet, $columns, $data, $first, $lastData, $last);
            $printEnd = $this->decorateTotal($sheet, $columns, $first, $lastData, $last);

            $sheet->setAutoFilter("A{$H}:{$last}{$lastData}");
        }

        $sheet->freezePane('A'.$first);
        $this->pageSetup($sheet, $last, $printEnd);
    }

    protected function decorateData(Worksheet $sheet, array $columns, array $data, int $first, int $lastData, string $last): void
    {
        $sheet->getStyle("A{$first}:{$last}{$lastData}")->applyFromArray([
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        foreach ($columns as $i => $column) {
            $L = Coordinate::stringFromColumnIndex($i + 1);
            $type = $column['type'];
            $style = $sheet->getStyle("{$L}{$first}:{$L}{$lastData}");

            $style->getNumberFormat()->setFormatCode($this->numberFormat($column, $i));

            if (in_array($type, ['no', 'center', 'date', 'datetime'], true)) {
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } elseif (in_array($type, ['int', 'qty', 'dec', 'money'], true)) {
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            } else {
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }

            if ($column['wrap']) {
                $style->getAlignment()->setWrapText(true);
            }

            // Kolom teks yang tampak seperti angka/rumus dipaksa tetap teks.
            if (in_array($type, ['text', 'code'], true)) {
                foreach ($data as $k => $row) {
                    $v = $row[$i] ?? null;
                    if (is_string($v) && $v !== '' && (is_numeric($v) || (strlen($v) > 1 && in_array($v[0], ['=', '+', '-', '@'], true)))) {
                        $sheet->setCellValueExplicit($L.($first + $k), $v, DataType::TYPE_STRING);
                    }
                }
            }
        }

        // Garis zebra lewat conditional formatting (satu aturan, ringan untuk data besar).
        $zebra = new Conditional();
        $zebra->setConditionType(Conditional::CONDITION_EXPRESSION);
        $zebra->addCondition('MOD(ROW(),2)=0');
        $zebra->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $zebra->getStyle()->getFill()->getEndColor()->setARGB(self::COLOR_ZEBRA);
        $sheet->getStyle("A{$first}:{$last}{$lastData}")->setConditionalStyles([$zebra]);
    }

    /** @return int baris terakhir area cetak */
    protected function decorateTotal(Worksheet $sheet, array $columns, int $first, int $lastData, string $last): int
    {
        $sumIdx = [];
        foreach ($columns as $i => $column) {
            if ($column['sum']) {
                $sumIdx[] = $i;
            }
        }

        if (! $sumIdx) {
            return $lastData;
        }

        $total = $lastData + 1;
        $firstSum = min($sumIdx);

        $sheet->getStyle("A{$total}:{$last}{$total}")->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_TOTAL]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]],
                'top'        => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLOR_HEADER_LINE]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($firstSum >= 1) {
            $sheet->setCellValueExplicit("A{$total}", 'TOTAL', DataType::TYPE_STRING);
            $endLabel = Coordinate::stringFromColumnIndex($firstSum); // kolom tepat sebelum kolom total pertama
            if ($firstSum >= 2) {
                $sheet->mergeCells("A{$total}:{$endLabel}{$total}");
            }
            $sheet->getStyle("A{$total}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach ($sumIdx as $i) {
            $L = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit("{$L}{$total}", "=SUBTOTAL(109,{$L}{$first}:{$L}{$lastData})", DataType::TYPE_FORMULA);
            $sheet->getStyle("{$L}{$total}")->getNumberFormat()->setFormatCode($this->numberFormat($columns[$i], $i));
            $sheet->getStyle("{$L}{$total}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getRowDimension($total)->setRowHeight(22);

        return $total;
    }

    protected function pageSetup(Worksheet $sheet, string $last, int $printEnd): void
    {
        $page = $sheet->getPageSetup();
        $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $page->setPaperSize(PageSetup::PAPERSIZE_A4);
        $page->setFitToPage(true);
        $page->setFitToWidth(1);
        $page->setFitToHeight(0);
        $page->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        $page->setPrintArea("A1:{$last}{$printEnd}");

        $margins = $sheet->getPageMargins();
        $margins->setTop(0.5);
        $margins->setBottom(0.6);
        $margins->setLeft(0.4);
        $margins->setRight(0.4);

        $sheet->getHeaderFooter()->setOddFooter('&L&8'.str_replace('&', '&&', $this->reportTitle()).'&R&8Halaman &P dari &N');
    }
}