<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mutasi Saldo - {{ $customer->name }}</title>
    @php
        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
        $tgl = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : null;
        $periode = $stmt['mulai'] && $stmt['selesai']
            ? $tgl($stmt['mulai']) . ' s.d. ' . $tgl($stmt['selesai'])
            : ($stmt['mulai'] ? 'mulai ' . $tgl($stmt['mulai']) : ($stmt['selesai'] ? 'sampai ' . $tgl($stmt['selesai']) : 'Semua tanggal'));
    @endphp
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; }
        .wrap { max-width: 190mm; margin: 0 auto; padding: 8px; }
        .head { text-align: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
        .head .nm { font-size: 18px; font-weight: 700; }
        .head .sm { font-size: 11px; color: #444; }
        h2 { text-align: center; font-size: 15px; margin: 8px 0 2px; letter-spacing: .5px; }
        .per { text-align: center; margin-bottom: 10px; color: #333; }
        .info { width: 100%; margin-bottom: 10px; }
        .info td { padding: 1px 0; vertical-align: top; }
        .info td:first-child { width: 90px; color: #555; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th, table.t td { border: 1px solid #999; padding: 5px 7px; }
        table.t th { background: #e9edf3; text-align: center; }
        table.t td.r { text-align: right; white-space: nowrap; }
        table.t td.c { text-align: center; white-space: nowrap; }
        table.t tr.edge td { background: #f2f4f7; font-weight: 700; }
        table.t tr { page-break-inside: avoid; }
        .foot { margin-top: 8px; font-size: 10px; color: #666; display: flex; justify-content: space-between; }
        .no-print { text-align: center; margin: 14px 0; }
        .no-print button { padding: 9px 22px; border: 0; border-radius: 6px; background: #111; color: #fff; font-size: 14px; cursor: pointer; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="no-print"><button onclick="window.print()">Cetak</button></div>

        <div class="head">
            <div class="nm">{{ $identity['name'] }}</div>
            @if ($identity['address'])<div class="sm">{{ $identity['address'] }}</div>@endif
            @if ($identity['contact'])<div class="sm">{{ $identity['contact'] }}</div>@endif
        </div>

        <h2>MUTASI SALDO CUSTOMER</h2>
        <div class="per">Periode: {{ $periode }}</div>

        <table class="info">
            <tr><td>Customer</td><td>: <b>{{ $customer->name }}</b></td></tr>
            @if ($customer->no_telp)<tr><td>No. Telp</td><td>: {{ $customer->no_telp }}</td></tr>@endif
            @if ($customer->alamat)<tr><td>Alamat</td><td>: {{ $customer->alamat }}</td></tr>@endif
        </table>

        <table class="t">
            <thead>
                <tr>
                    <th style="width:75px">Tanggal</th>
                    <th>Keterangan</th>
                    <th style="width:100px">Top Up (masuk)</th>
                    <th style="width:110px">Pengurangan (keluar)</th>
                    <th style="width:105px">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr class="edge">
                    <td class="c">{{ $tgl($stmt['mulai']) }}</td>
                    <td>Saldo Awal</td>
                    <td></td>
                    <td></td>
                    <td class="r">{{ $fmt($stmt['saldo_awal']) }}</td>
                </tr>
                @forelse ($stmt['rows'] as $r)
                    <tr>
                        <td class="c">{{ $r['tanggal']->format('d/m/Y') }}</td>
                        <td>{{ $r['note'] !== '' ? $r['note'] : '—' }}</td>
                        <td class="r">{{ $r['topup'] !== null ? $fmt($r['topup']) : '' }}</td>
                        <td class="r">{{ $r['pengurangan'] !== null ? $fmt($r['pengurangan']) : '' }}</td>
                        <td class="r">{{ $fmt($r['saldo']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#777;padding:14px">Tidak ada mutasi pada periode ini.</td></tr>
                @endforelse
                <tr class="edge">
                    <td class="c">{{ $tgl($stmt['selesai']) }}</td>
                    <td>Saldo Akhir &nbsp; <span style="font-weight:400;color:#555">(total mutasi periode)</span></td>
                    <td class="r">{{ $fmt($stmt['total_topup']) }}</td>
                    <td class="r">{{ $fmt($stmt['total_pengurangan']) }}</td>
                    <td class="r">{{ $fmt($stmt['saldo_akhir']) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="foot">
            <span>Saldo hanya catatan, tidak dipakai di kasir.</span>
            <span>Dicetak oleh {{ auth()->user()->name }} pada {{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>
</body>

</html>