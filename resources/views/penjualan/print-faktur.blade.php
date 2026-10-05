<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faktur {{ $penjualan->code }}</title>
    <script>
        // Simpan pilihan kertas per-perangkat (sama dengan halaman struk 58 / 80).
        (function () {
            try {
                var url = new URL(window.location.href);
                var chosen = url.searchParams.get('paper');
                if (chosen === '58' || chosen === '80' || chosen === 'faktur') {
                    window.localStorage.setItem('receipt-paper', chosen);
                    return;
                }
                var saved = window.localStorage.getItem('receipt-paper');
                if (saved === '58' || saved === '80' || saved === 'faktur') {
                    url.searchParams.set('paper', saved);
                    window.location.replace(url.toString());
                }
            } catch (e) {}
        })();
    </script>
    @php
        $items = $penjualan->items;
        $itemCount = $items->sum(fn ($item) => (int) $item->qty);

        // Rumus sama persis dengan struk (print.blade.php) supaya angka konsisten.
        $lineBase = fn ($item) => (float) (
            $item->base_subtotal
                ?? ($item->base_price !== null
                    ? (float) $item->qty * (float) $item->base_price
                    : ($item->subtotal ?? ((float) $item->qty * (float) $item->price)))
        );
        $subtotalBeforePromotion = $items->sum($lineBase);
        $promotionTotal = (float) ($penjualan->promotion_total ?? 0);
        $voucherTotal = (float) ($penjualan->voucher_total ?? 0);
        $grandTotal = (float) ($penjualan->grand_total
            ?? max(0, $subtotalBeforePromotion - $promotionTotal - $voucherTotal));
        $paidAmount = (float) ($penjualan->paid_amount ?? $penjualan->total ?? 0);
        $changeAmount = (float) ($penjualan->change_amount ?? max(0, $paidAmount - $grandTotal));
        $money = fn ($value) => number_format((float) $value, 0, ',', '.');
    @endphp
    <style>
        /* Continuous form 9,5 x 11 inch = 24,13 x 27,94 cm */
        @page { size: 24.13cm 27.94cm; margin: 1cm 0 1cm 1.8cm; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            width: 18cm; /* lebar area cetak aman (di dalam tractor + batas printer) */
            margin: 0 auto;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
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
        @media screen {
            body { padding-top: 0.5cm; }
        }
        @media print {
            body { width: 18cm; margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>

<body>
    <div class="row">
        <div class="col-left">
            <img class="store-logo" src="{{ asset('img/logo.png') }}" alt="{{ $penjualan->outlet?->name ?? 'LUWES' }}">
            <div class="store-name">{{ $penjualan->outlet?->name ?? 'LUWES' }}</div>
            @if ($penjualan->outlet?->alamat)<div class="small">{{ $penjualan->outlet->alamat }}</div>@endif
            @if ($penjualan->outlet?->desc)<div class="small">{{ $penjualan->outlet->desc }}</div>@endif
        </div>
        <div class="col-right">
            <div class="doc-title">FAKTUR PENJUALAN</div>
            <table class="meta">
                <tr><td>No. Faktur</td><td>:</td><td>{{ $penjualan->code }}</td></tr>
                <tr><td>Tanggal</td><td>:</td><td>{{ optional($penjualan->created_at)->format('d/m/Y H:i') }}</td></tr>
                <tr><td>Kasir</td><td>:</td><td>{{ $penjualan->cashierShift?->name ?? 'Kasir' }}</td></tr>
                <tr><td>Customer</td><td>:</td><td>{{ $penjualan->customer?->name ?? 'Umum' }}</td></tr>
                <tr><td>Pembayaran</td><td>:</td><td>{{ $penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai' }}</td></tr>
            </table>
        </div>
    </div>

    @if ($penjualan->customer?->alamat || $penjualan->customer?->no_telp)
        <hr>

        <table class="meta">
            @if ($penjualan->customer?->alamat)<tr><td>Alamat</td><td>:</td><td>{{ $penjualan->customer->alamat }}</td></tr>@endif
            @if ($penjualan->customer?->no_telp)<tr><td>No. Telp</td><td>:</td><td>{{ $penjualan->customer->no_telp }}</td></tr>@endif
        </table>
    @endif

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
                @php
                    $unitPrice = (float) $item->price;
                    $referencePrice = (float) ($item->harga_aktif ?? $item->base_price ?? $unitPrice);
                    $discountPerUnit = max(0, $referencePrice - $unitPrice);
                    $discountTotal = $discountPerUnit * (float) $item->qty;
                @endphp
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>
                        {{ $item->product?->name ?? 'Produk' }}
                        @if ($discountTotal > 0)
                            <div class="small">Diskon item {{ $item->qty }} x {{ $money($discountPerUnit) }} = -{{ $money($discountTotal) }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $item->qty }} {{ $item->product?->satuan }}</td>
                    <td class="num">{{ $money($unitPrice) }}</td>
                    <td class="num">{{ $money($lineBase($item)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row summary">
        <div class="notes small">
            Jumlah Item : {{ $itemCount }}
            @if ($penjualan->payment_reference)<br>Ref. Bayar : {{ $penjualan->payment_reference }}@endif
        </div>
        <table class="totals">
            <tr><td class="label">Subtotal</td><td class="value">@currency($subtotalBeforePromotion)</td></tr>
            @if ($promotionTotal > 0)<tr><td class="label">Diskon Rafaksi</td><td class="value">-@currency($promotionTotal)</td></tr>@endif
            @if ($voucherTotal > 0)<tr><td class="label">Voucher</td><td class="value">-@currency($voucherTotal)</td></tr>@endif
            <tr class="grand"><td class="label">TOTAL</td><td class="value">@currency($grandTotal)</td></tr>
            <tr><td class="label">Dibayar</td><td class="value">@currency($paidAmount)</td></tr>
            <tr><td class="label">Kembali</td><td class="value">@currency($changeAmount)</td></tr>
        </table>
    </div>

    <div class="signs">
        <div><div>Penerima,</div><div class="space"></div><div class="line"></div></div>
        <div><div>Hormat Kami,</div><div class="space"></div><div class="line"></div></div>
    </div>

    @if ($penjualan->outlet?->footer)
        <div class="footer-msg">{!! $penjualan->outlet->footer !!}</div>
    @else
        <div class="footer-msg">Harga Barang Sudah termasuk PPN &mdash; Terima Kasih Atas Kunjungan Anda</div>
    @endif
    <div class="follow">Follow Us <svg style="width:1.1em;height:1.1em;vertical-align:-0.2em" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#000" stroke="none"/></svg> @mmccomputerpacitan</div>

    <div class="no-print">
        <a href="{{ route('outlet.show', $penjualan->outlet_id) }}">Kembali</a>
        <button type="button" class="primary" onclick="window.print(); return false;">Print Faktur</button>
        <div class="papers">
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '58']) }}">Kertas 58mm</a>
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '80']) }}">Kertas 80mm</a>
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => 'faktur']) }}" class="active">Faktur</a>
        </div>
        <div class="tips">
            <b>Supaya pas di continuous form:</b><br>
            1. Di dialog print pilih printer dot matrix-nya, ukuran kertas <b>24,13 x 27,94 cm</b> (9,5 x 11 inch). Kalau belum ada, buat custom paper size di Printer Properties.<br>
            2. Margins: <b>Default/None</b>, Scale: <b>100%</b> (jangan "Fit to page").<br>
            3. Matikan <b>Headers and footers</b>.
        </div>
    </div>

    @if (request('auto'))
        <script>
            (function () {
                var printed = false;
                function doPrint() {
                    if (printed) return;
                    printed = true;
                    setTimeout(function () { window.print(); }, 150);
                }
                window.addEventListener('load', function () {
                    var pending = Array.prototype.filter.call(document.images, function (img) { return !img.complete; });
                    if (!pending.length) return doPrint();
                    var left = pending.length;
                    pending.forEach(function (img) {
                        var done = function () { if (--left <= 0) doPrint(); };
                        img.addEventListener('load', done);
                        img.addEventListener('error', done);
                    });
                    setTimeout(doPrint, 2000);
                });
                window.addEventListener('afterprint', function () { window.location.href = @json(route('outlet.show', $penjualan->outlet_id)); });
            })();
        </script>
    @endif
</body>

</html>