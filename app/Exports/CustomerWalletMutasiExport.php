<?php

namespace App\Exports;

use App\Exports\Support\RequestReportExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mutasi Saldo Customer — format mutasi rekening untuk SATU customer.
 *
 * Kolom : Tanggal | Keterangan | Top Up | Pengurangan | Saldo
 * Baris : Saldo Awal (pertama), mutasi, Saldo Akhir (terakhir), lalu TOTAL top up & pengurangan.
 * Data  : hasil CustomerWalletService::statement().
 */
class CustomerWalletMutasiExport extends RequestReportExport
{
    public function __construct(Request $request, private readonly User $customer, private readonly array $stmt)
    {
        parent::__construct($request);
    }

    protected function fileSlug(): string
    {
        return 'Mutasi-Saldo-'.Str::slug($this->customer->name);
    }

    public function downloadName(): string
    {
        return self::fileName($this->fileSlug(), $this->stmt['mulai'], $this->stmt['selesai']);
    }

    protected function reportTitle(): string
    {
        return 'Mutasi Saldo Customer';
    }

    protected function sheetName(): string
    {
        return 'Mutasi Saldo';
    }

    protected function periodText(): ?string
    {
        $mulai = $this->stmt['mulai'];
        $selesai = $this->stmt['selesai'];
        $fmt = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/Y');

        if ($mulai && $selesai) {
            return 'Periode: '.$fmt($mulai).' s.d. '.$fmt($selesai);
        }
        if ($mulai) {
            return 'Periode: mulai '.$fmt($mulai);
        }
        if ($selesai) {
            return 'Periode: sampai '.$fmt($selesai);
        }

        return 'Periode: Semua Tanggal';
    }

    protected function filterTexts(): array
    {
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');

        return [
            'Customer: '.$this->customer->name.($this->customer->no_telp ? ' ('.$this->customer->no_telp.')' : ''),
            'Saldo Awal: '.$rp($this->stmt['saldo_awal']),
            'Saldo Akhir: '.$rp($this->stmt['saldo_akhir']),
        ];
    }

    protected function columns(): array
    {
        return [
            self::col('Tanggal', 'date', 13),
            self::col('Keterangan', 'text', 46, ['wrap' => true]),
            self::col('Top Up (Masuk)', 'money', 18, ['sum' => true]),
            self::col('Pengurangan (Keluar)', 'money', 20, ['sum' => true]),
            self::col('Saldo', 'money', 18),
        ];
    }

    protected function rows(): iterable
    {
        yield [$this->stmt['mulai'], 'SALDO AWAL', null, null, $this->stmt['saldo_awal']];

        foreach ($this->stmt['rows'] as $r) {
            yield [
                $r['tanggal'],
                $r['note'] !== '' ? $r['note'] : '-',
                $r['topup'],
                $r['pengurangan'],
                $r['saldo'],
            ];
        }

        yield [$this->stmt['selesai'], 'SALDO AKHIR', null, null, $this->stmt['saldo_akhir']];
    }

    /** Baris Saldo Awal dan Saldo Akhir ditebalkan. */
    protected function decorateData(Worksheet $sheet, array $columns, array $data, int $first, int $lastData, string $last): void
    {
        parent::decorateData($sheet, $columns, $data, $first, $lastData, $last);

        foreach ([$first, $lastData] as $row) {
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
        }
    }
}