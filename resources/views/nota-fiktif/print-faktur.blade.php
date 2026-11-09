<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota {{ $nota->code }}</title>
    @php
        $items = $nota->items;
        $itemCount = $items->sum(fn ($item) => (float) $item->qty);
        $money = fn ($value) => number_format((float) $value, 0, ',', '.');
        $fmtQty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', '.'), '0'), ',');
    @endphp
    <style>
        /* Continuous form 9,5 x 11 inch = 24,13 x 27,94 cm */
        /* @page margin sengaja 0: Chrome mengabaikan margin @page kalau dialog print = "None".
           Posisi isi diatur lewat variabel di bawah supaya hasilnya sama untuk Default maupun None. */
        @page { size: 24.13cm 27.94cm; margin: 0; }
        :root {
            --geser-kiri: 1cm;   /* KALIBRASI: besarkan kalau huruf kiri masih terpotong / isi kurang ke kanan */
            --geser-atas: 1cm;   /* besarkan kalau isi mau turun, kecilkan kalau mau naik */
            --lebar-isi: 17.8cm; /* kecilkan kalau kanan terpotong, besarkan kalau terlalu jauh dari kanan */
        }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
        }
        .sheet {
            width: var(--lebar-isi);
            margin: var(--geser-atas) 0 0 var(--geser-kiri);
            padding-left: 0.3cm; /* cadangan supaya huruf pertama (mis. "J") tidak terpotong */
        }
        .row { display: flex; justify-content: space-between; gap: 0.6cm; }
        .col-left { flex: 1 1 55%; }
        .col-right { flex: 1 1 45%; }
        .store-logo { max-height: 1.6cm; max-width: 4cm; margin-bottom: 0.1cm; filter: grayscale(1) contrast(2); }
        .store-name { font-size: 13pt; font-weight: bold; }
        .doc-title { font-size: 15pt; font-weight: bold; text-align: right; letter-spacing: 1px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 0.1cm; }
        .meta td { padding: 0 0.1cm 0 0; vertical-align: top; }
        .meta td:first-child { white-space: nowrap; width: 2.6cm; }
        .meta td:nth-child(2) { width: 0.3cm; }
        hr { border: none; border-top: 1px solid #000; margin: 0.25cm 0; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items thead { display: table-header-group; } /* header ikut diulang di halaman berikutnya */
        table.items th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 0.1cm 0.15cm; text-align: left; }
        table.items th.num { text-align: right; }
        table.items th.center { text-align: center; }
        table.items td { padding: 0.08cm 0.15cm; vertical-align: top; }
        table.items tr { page-break-inside: avoid; }
        table.items tbody tr:last-child td { border-bottom: 1px solid #000; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .small { font-size: 9pt; }
        .col-no { width: 1cm; }
        .col-qty { width: 2cm; }
        .col-price { width: 2.6cm; }
        .col-total { width: 2.8cm; }

        .summary { page-break-inside: avoid; margin-top: 0.3cm; }
        .notes { flex: 1 1 55%; }
        .totals { flex: 0 0 8.6cm; border-collapse: collapse; }
        .totals td { padding: 0.03cm 0.15cm; }
        .totals .label { text-align: left; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .grand td { border-top: 1px solid #000; border-bottom: 1px solid #000; font-weight: bold; font-size: 11pt; }

        .signs { display: flex; justify-content: space-around; margin-top: 0.6cm; text-align: center; page-break-inside: avoid; }
        .signs div { width: 5cm; }
        .signs .space { height: 1.6cm; }
        .signs .line { border-top: 1px solid #000; }
        .footer-msg { margin-top: 0.4cm; text-align: center; font-size: 9pt; }
        .follow { margin-top: 0.1cm; text-align: center; font-size: 9pt; }

        /* Tampilan layar saja (tombol & petunjuk) */
        .no-print { margin: 0.6cm auto 0; width: 100%; max-width: 12cm; font-family: Arial, sans-serif; font-size: 14px; }
        .no-print a, .no-print button { display: block; width: 100%; margin-top: 6px; padding: 10px; border: 1px solid #777; background: #fff; color: #000; font: inherit; text-align: center; text-decoration: none; cursor: pointer; border-radius: 4px; }
        .no-print .primary { background: #111; color: #fff; border-color: #111; font-weight: bold; }
        .no-print .papers { display: flex; gap: 6px; flex-wrap: wrap; }
        .no-print .papers a { flex: 1 1 30%; }
        .no-print .papers a.active { background: #e8f0fe; border-color: #1a56db; font-weight: bold; }
        .no-print .tips { margin-top: 10px; padding: 8px 10px; background: #fff8e1; border: 1px solid #f0d98a; border-radius: 4px; font-size: 12px; line-height: 1.5; text-align: left; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>

<body>
<div class="sheet">
    <div class="row">
        <div class="col-left">
            <img class="store-logo" src="{{ asset('img/logo.png') }}" alt="{{ $nota->outlet_nama }}">
            <div class="store-name">{{ $nota->outlet_nama }}</div>
            <div class="small">CV Kirana Artha Persada</div>
            @if ($nota->outlet_alamat)<div class="small">{{ $nota->outlet_alamat }}</div>@endif
        </div>
        <div class="col-right">
            <div class="doc-title">NOTA PENJUALAN</div>
            <table class="meta">
                <tr><td>No. Faktur</td><td>:</td><td>{{ $nota->code }}</td></tr>
                <tr><td>Tanggal</td><td>:</td><td>{{ $nota->tanggal->format('d/m/Y H:i') }}</td></tr>
                <tr><td>Kasir</td><td>:</td><td>{{ $nota->kasir_nama ?: 'Kasir' }}</td></tr>
                <tr><td>Customer</td><td>:</td><td>{{ $nota->customer_nama ?: 'Umum' }}</td></tr>
                <tr><td>Pembayaran</td><td>:</td><td>{{ $nota->metode_bayar ?: 'Tunai' }}</td></tr>
            </table>
        </div>
    </div>

    <hr>

    <table class="items">
        <thead>
            <tr>
                <th class="col-no center">No</th>
                <th>Nama Barang</th>
                <th class="col-qty num">Qty</th>
                <th class="col-price num">Harga</th>
                <th class="col-total num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $item->nama }}</td>
                    <td class="num">{{ $fmtQty($item->qty) }} {{ $item->satuan }}</td>
                    <td class="num">{{ $money($item->harga) }}</td>
                    <td class="num">{{ $money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row summary">
        <div class="notes small">
            Jumlah Item : {{ $fmtQty($itemCount) }}
            @if ($nota->referensi)<br>Ref. Bayar : {{ $nota->referensi }}@endif
        </div>
        <table class="totals">
            <tr><td class="label">Subtotal</td><td class="value">@currency($nota->subtotal)</td></tr>
            @if ($nota->diskon > 0)<tr><td class="label">Diskon</td><td class="value">-@currency($nota->diskon)</td></tr>@endif
            <tr class="grand"><td class="label">TOTAL</td><td class="value">@currency($nota->total)</td></tr>
            <tr><td class="label">Dibayar</td><td class="value">@currency($nota->bayar)</td></tr>
            <tr><td class="label">Kembali</td><td class="value">@currency($nota->kembali)</td></tr>
        </table>
    </div>

    <div class="signs">
        <div><div>Penerima,</div><div class="space"></div><div class="line"></div></div>
        <div><div>Hormat Kami,</div><div class="space"></div><div class="line"></div></div>
    </div>

    @if ($nota->footer)
        <div class="footer-msg" style="white-space: pre-line">{{ $nota->footer }}</div>
    @endif
    <div class="follow">Follow Us <svg style="width:1.1em;height:1.1em;vertical-align:-0.2em" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#000" stroke="none"/></svg> @mmccomputerpacitan</div>

</div>{{-- /.sheet --}}

    <div class="no-print">
        <a href="{{ route('nota-fiktif.index') }}">Kembali ke Riwayat</a>
        <button type="button" class="primary" onclick="window.print(); return false;">Print Faktur</button>
        <div class="papers">
            <a href="{{ route('nota-fiktif.show', [$nota, 'paper' => '58']) }}">Kertas 58mm</a>
            <a href="{{ route('nota-fiktif.show', [$nota, 'paper' => '80']) }}">Kertas 80mm</a>
            <a href="{{ route('nota-fiktif.show', [$nota, 'paper' => 'faktur']) }}" class="active">Faktur</a>
        </div>
        <a href="{{ route('nota-fiktif.edit', $nota) }}">Edit Nota</a>
        <div class="tips">
            <b>Supaya pas di continuous form:</b><br>
            1. Di dialog print pilih printer dot matrix-nya, ukuran kertas <b>24,13 x 27,94 cm</b> (9,5 x 11 inch).<br>
            2. Margins: <b>None</b> atau Default (hasilnya sama), Scale: <b>100%</b> (jangan "Fit to page").<br>
            3. Matikan <b>Headers and footers</b>.
        </div>
    </div>
</body>

</html>