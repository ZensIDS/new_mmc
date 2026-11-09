<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota {{ $nota->code }}</title>
    @php
        $paper = in_array(request('paper'), ['58', '80'], true) ? request('paper') : '58';
        $printable = $paper === '58' ? '48mm' : '72mm';
        $baseFont = $paper === '58' ? '12px' : '14px';
        $smallFont = $paper === '58' ? '11px' : '12px';
        $titleFont = $paper === '58' ? '16px' : '20px';
        $totalFont = $paper === '58' ? '15px' : '18px';
        $itemCount = $nota->items->sum(fn ($i) => (float) $i->qty);
        $fmtQty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', '.'), '0'), ',');
    @endphp
    <style>
        @page { size: {{ $paper }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { width: {{ $printable }}; margin: 0 auto; padding: 2mm 0 8mm;
            font-family: 'Lucida Console', Consolas, 'Courier New', monospace; letter-spacing: .2px;
            font-size: {{ $baseFont }}; color: #000; line-height: 1.3; }
        .center { text-align: center; }
        .store-logo { max-width: {{ $paper === '58' ? '28mm' : '40mm' }}; max-height: 18mm; margin-bottom: 1mm; filter: grayscale(1) contrast(2); }
        .store-name { font-size: {{ $titleFont }}; letter-spacing: .5px; word-break: break-word; }
        .small { font-size: {{ $smallFont }}; }
        hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .meta td { padding: 1px 0; }
        .meta td:first-child { white-space: nowrap; padding-right: 3px; }
        .meta td:last-child { text-align: right; word-break: break-word; }
        .item-row td { padding: 1px 0; }
        .item-name { word-break: break-word; overflow-wrap: anywhere; }
        .qty-price { font-size: {{ $smallFont }}; }
        .qty-price td:first-child { white-space: nowrap; }
        .price-col { text-align: right; white-space: nowrap; padding-left: 3px; }
        .totals td { padding: 1px 0; }
        .totals .value { text-align: right; white-space: nowrap; padding-left: 3px; }
        .grand-total { font-size: {{ $totalFont }}; }
        .footer-msg { margin-top: 6px; font-size: {{ $smallFont }}; word-break: break-word; white-space: pre-line; }
        .no-print { margin: 14px auto 0; width: 100%; max-width: 340px; font-family: Arial, sans-serif; font-size: 14px; }
        .no-print a, .no-print button { display: block; width: 100%; margin-top: 6px; padding: 10px; border: 1px solid #777; background: #fff; color: #000; font: inherit; text-align: center; text-decoration: none; cursor: pointer; border-radius: 4px; }
        .no-print .primary { background: #111; color: #fff; border-color: #111; font-weight: bold; }
        .no-print .papers { display: flex; gap: 6px; }
        .no-print .papers a.active { background: #e8f0fe; border-color: #1a56db; font-weight: bold; }
        .no-print .tips { margin-top: 10px; padding: 8px 10px; background: #fff8e1; border: 1px solid #f0d98a; border-radius: 4px; font-size: 12px; line-height: 1.5; }
        @media print { body { margin: 0; } .no-print { display: none !important; } }
    </style>
</head>

<body>
    <div class="center">
        <img class="store-logo" src="{{ asset('img/logo.png') }}" alt="{{ $nota->outlet_nama }}">
        <div class="store-name">{{ $nota->outlet_nama }}</div>
        @if ($nota->outlet_alamat)<div class="small">{{ $nota->outlet_alamat }}</div>@endif
    </div>

    <hr>

    <table class="meta small">
        <tr><td>No. Struk</td><td>{{ $nota->code }}</td></tr>
        <tr><td>Tanggal</td><td>{{ $nota->tanggal->format('d/m/Y H:i') }}</td></tr>
        @if ($nota->kasir_nama)<tr><td>Kasir</td><td>{{ $nota->kasir_nama }}</td></tr>@endif
        @if ($nota->customer_nama)<tr><td>Customer</td><td>{{ $nota->customer_nama }}</td></tr>@endif
    </table>

    <hr>

    <table>
        @foreach ($nota->items as $item)
            <tr class="item-row"><td colspan="2" class="item-name">{{ $item->nama }}</td></tr>
            <tr class="item-row qty-price">
                <td>{{ $fmtQty($item->qty) }}{{ $item->satuan ? ' ' . $item->satuan : '' }} x {{ number_format($item->harga, 0, ',', '.') }}</td>
                <td class="price-col">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <hr>

    <table class="totals">
        <tr><td>Jumlah Item</td><td class="value">{{ $fmtQty($itemCount) }}</td></tr>
        <tr><td>Subtotal</td><td class="value">@currency($nota->subtotal)</td></tr>
        @if ($nota->diskon > 0)<tr><td>Diskon</td><td class="value">-@currency($nota->diskon)</td></tr>@endif
        <tr class="grand-total"><td>TOTAL</td><td class="value">@currency($nota->total)</td></tr>
    </table>

    <hr>

    <table class="totals">
        <tr><td>{{ $nota->metode_bayar ?: 'Tunai' }}</td><td class="value">@currency($nota->bayar)</td></tr>
        <tr><td>Kembali</td><td class="value">@currency($nota->kembali)</td></tr>
        @if ($nota->referensi)<tr class="small"><td>Ref.</td><td class="value">{{ $nota->referensi }}</td></tr>@endif
    </table>

    <hr>

    @if ($nota->footer)
        <div class="center footer-msg">{{ $nota->footer }}</div>
    @endif

    <div class="no-print">
        <a href="{{ route('nota-fiktif.index') }}">Kembali ke Riwayat</a>
        <button type="button" class="primary" onclick="window.print(); return false;">Print {{ $paper }}mm</button>
        <div class="papers">
            <a href="{{ route('nota-fiktif.show', [$nota, 'paper' => '58']) }}" class="{{ $paper === '58' ? 'active' : '' }}">Kertas 58mm</a>
            <a href="{{ route('nota-fiktif.show', [$nota, 'paper' => '80']) }}" class="{{ $paper === '80' ? 'active' : '' }}">Kertas 80mm</a>
            <a href="{{ route('nota-fiktif.show', [$nota, 'paper' => 'faktur']) }}">Faktur</a>
        </div>
        <a href="{{ route('nota-fiktif.edit', $nota) }}">Edit Nota</a>
        <div class="tips">
            Di dialog print: pilih ukuran kertas <b>{{ $paper }}mm</b>, Margins <b>None</b>, Scale <b>100%</b>, matikan <b>Headers and footers</b>.
        </div>
    </div>
</body>

</html>