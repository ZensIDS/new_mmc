<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-size: 9px; }
        .meta { font-size: 8px; color: #444; margin: 0 0 6px; }
        .meta span { margin-right: 14px; }
        h4 { font-size: 9.5px; margin: 10px 0 3px; }
        .sum td:first-child { width: 60%; }
        .total td { font-weight: bold; background: #E2EAF7; }
        .note { font-size: 7.5px; color: #666; margin-top: 3px; }
        .foot { margin-top: 12px; font-size: 7.5px; color: #666; }
        .col2 { width: 49%; display: inline-block; vertical-align: top; }
    </style>
</head>
<body>
@include('exports.pdf._header')

@php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $isDetail = $mode === 'detail';
@endphp

<div class="report-title">REKAP PENJUALAN {{ $isDetail ? '(DETAIL)' : '(RINGKAS)' }}</div>
<div class="report-periode">Periode: {{ $filter->periodLabel() }}</div>

<div class="meta">
    <span>Outlet: <b>{{ $outletName }}</b></span>
    <span>Kasir: <b>{{ $kasirName }}</b></span>
    @if ($filter->method !== '')
        <span>Metode: <b>{{ $filter->method === 'tunai' ? 'Tunai (tanpa metode terdaftar)' : (\App\Models\PaymentMethod::withTrashed()->find($filter->method)?->name ?? '-') }}</b></span>
    @endif
    @if ($filter->status !== 'all')
        <span>Status: <b>{{ $filter->status === 'retur' ? 'Ada retur' : 'Normal' }}</b></span>
    @endif
    @if ($filter->search !== '')
        <span>Pencarian: <b>{{ $filter->search }}</b></span>
    @endif
</div>

@if ($isDetail)
    @php $sumSub = 0; $sumPot = 0; $sumGrand = 0; @endphp
    <table>
        <thead>
            <tr>
                <th style="width:24px">No</th>
                <th>Tanggal</th>
                <th>Invoice</th>
                <th>Outlet</th>
                <th>Kasir (shift)</th>
                <th>Item</th>
                <th>Metode</th>
                <th>Subtotal</th>
                <th>Potongan</th>
                <th>Grand Total</th>
                <th>Retur</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $p)
                @php
                    $sub = (float) ($p->subtotal ?? $p->total ?? 0);
                    $pot = (float) ($p->discount_total ?? $p->discount ?? 0) + (float) ($p->promotion_total ?? 0) + (float) ($p->voucher_total ?? 0);
                    $grand = (float) ($p->grand_total ?? ((float) $p->total - (float) $p->discount));
                    $sumSub += $sub; $sumPot += $pot; $sumGrand += $grand;
                @endphp
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td>{{ $p->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $p->code }}</td>
                    <td>{{ $p->outlet?->name ?? '-' }}</td>
                    <td>{{ $p->kasir?->name ?? '-' }}@if ($p->cashierShift?->name) ({{ $p->cashierShift->name }})@endif</td>
                    <td class="tc">{{ $p->items_count }}</td>
                    <td>{{ $p->paymentMethod?->name ?? $p->payment_method_name ?? 'Tunai' }}</td>
                    <td class="tr">{{ $rp($sub) }}</td>
                    <td class="tr">{{ $rp($pot) }}</td>
                    <td class="tr">{{ $rp($grand) }}</td>
                    <td class="tc">{{ $p->refund_penjualans_count > 0 ? 'Ya' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="tc">Tidak ada data</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr class="total">
                    <td colspan="7" class="tr">TOTAL ({{ $rows->count() }} transaksi)</td>
                    <td class="tr">{{ $rp($sumSub) }}</td>
                    <td class="tr">{{ $rp($sumPot) }}</td>
                    <td class="tr">{{ $rp($sumGrand) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
    <div class="note">Grand Total adalah nilai nota sebelum retur / ganti barang.</div>
@else
    <h4>Ringkasan</h4>
    <table class="sum">
        <tbody>
            <tr><td>Jumlah transaksi</td><td class="tr">{{ number_format($summary['transaksi'], 0, ',', '.') }}</td></tr>
            <tr><td>Total penjualan (sebelum potongan)</td><td class="tr">{{ $rp($summary['subtotal']) }}</td></tr>
            <tr><td>Diskon toko</td><td class="tr">{{ $rp($summary['diskon']) }}</td></tr>
            <tr><td>Promo</td><td class="tr">{{ $rp($summary['promo']) }}</td></tr>
            <tr><td>Voucher</td><td class="tr">{{ $rp($summary['voucher']) }}</td></tr>
            <tr class="total"><td>Grand Total (nilai nota, sebelum retur)</td><td class="tr">{{ $rp($summary['grand']) }}</td></tr>
        </tbody>
    </table>

    <h4>Per Metode Pembayaran</h4>
    <table>
        <thead><tr><th>Metode</th><th style="width:60px">Transaksi</th><th style="width:110px">Total</th></tr></thead>
        <tbody>
            @forelse ($summary['metode'] as $m)
                <tr><td>{{ $m['metode'] }}</td><td class="tc">{{ $m['trx'] }}</td><td class="tr">{{ $rp($m['total']) }}</td></tr>
            @empty
                <tr><td colspan="3" class="tc">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h4>Per Kasir</h4>
    <table>
        <thead><tr><th>Kasir</th><th style="width:60px">Transaksi</th><th style="width:110px">Total</th></tr></thead>
        <tbody>
            @forelse ($perKasir as $r)
                <tr><td>{{ $r['nama'] }}</td><td class="tc">{{ $r['trx'] }}</td><td class="tr">{{ $rp($r['total']) }}</td></tr>
            @empty
                <tr><td colspan="3" class="tc">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h4>Per Outlet</h4>
    <table>
        <thead><tr><th>Outlet</th><th style="width:60px">Transaksi</th><th style="width:110px">Total</th></tr></thead>
        <tbody>
            @forelse ($perOutlet as $r)
                <tr><td>{{ $r['nama'] }}</td><td class="tc">{{ $r['trx'] }}</td><td class="tr">{{ $rp($r['total']) }}</td></tr>
            @empty
                <tr><td colspan="3" class="tc">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h4>Retur / Ganti Barang</h4>
    <table class="sum">
        <tbody>
            <tr><td>Jumlah retur (pada nota di periode ini)</td><td class="tr">{{ $summary['retur']['jumlah'] }} ({{ $summary['retur']['nota'] }} nota)</td></tr>
            <tr><td>Nilai barang diretur</td><td class="tr">{{ $rp($summary['retur']['nilai']) }}</td></tr>
            <tr><td>Nilai barang pengganti</td><td class="tr">{{ $rp($summary['retur']['pengganti']) }}</td></tr>
        </tbody>
    </table>

    <h4>Kas</h4>
    <table class="sum">
        <tbody>
            <tr><td>Bon / kas keluar</td><td class="tr">{{ $rp($bon) }}</td></tr>
            <tr><td>Setoran akhir</td><td class="tr">{{ $rp($setoran) }}</td></tr>
        </tbody>
    </table>
    <div class="note">Bon dan setoran akhir mengikuti periode, outlet, dan kasir (sama seperti Laporan Penjualan Outlet); tidak mengikuti filter metode, status, atau pencarian.</div>
@endif

<div class="foot">Dicetak oleh {{ $printedBy ?? '-' }} pada {{ $printedAt->format('d/m/Y H:i') }}</div>
</body>
</html>