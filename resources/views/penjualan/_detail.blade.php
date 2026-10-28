{{-- Detail satu nota untuk baris yang di-expand di /penjualan (dimuat saat dibuka). --}}
@php
    $totalCost = 0;
    foreach ($penjualan->items as $it) {
        $totalCost += $it->subtotal ?? ($it->qty * $it->price);
    }
    $discount = $penjualan->discount_total ?? $penjualan->discount ?? 0;
    $grand = $penjualan->grand_total ?? ($totalCost - ($penjualan->discount ?? 0) - ($penjualan->voucher?->value ?? 0));
@endphp
<div class="pj-detail">
    <div class="row">
        <div class="col-md-8">
            <table class="table table-condensed table-bordered" style="background:#fff;margin-bottom:0">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right" style="width:90px">Banyak</th>
                        <th class="text-right" style="width:140px">Harga Jual</th>
                        <th class="text-right" style="width:150px">Sub total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($penjualan->items as $item)
                        <tr>
                            <td>{{ $item->serial_number ?: $item->product?->code }} - {{ $item->product?->name ?? '(produk dihapus)' }}</td>
                            <td class="text-right">{{ $item->qty }}</td>
                            <td class="text-right">@currency($item->price)</td>
                            <td class="text-right">@currency($item->subtotal ?? ($item->qty * $item->price))</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Tidak ada item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="col-md-4">
            <table class="table table-condensed table-bordered" style="background:#fff;margin-bottom:8px">
                <tr><td>Subtotal</td><td class="text-right">@currency($penjualan->subtotal ?? $totalCost)</td></tr>
                <tr><td>Disc Toko</td><td class="text-right">@currency($discount)</td></tr>
                <tr><td>Promo</td><td class="text-right">@currency($penjualan->promotion_total ?? 0)</td></tr>
                <tr><td>Voucher</td><td class="text-right">@currency($penjualan->voucher_total ?? 0)</td></tr>
                <tr><th>Grand Total</th><th class="text-right">@currency($grand)</th></tr>
                <tr><td>Dibayar</td><td class="text-right">@currency($penjualan->paid_amount ?? $penjualan->total ?? 0)</td></tr>
                <tr><td>Kembalian</td><td class="text-right">@currency($penjualan->change_amount ?? 0)</td></tr>
            </table>
            <div class="text-muted" style="font-size:12px;line-height:1.6">
                Customer: <b>{{ $penjualan->customer?->name ?? 'Umum' }}</b><br>
                Metode: <b>{{ $penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai' }}</b>
                @if ($penjualan->payment_reference)
                    &middot; Ref: <b>{{ $penjualan->payment_reference }}</b>
                @endif
                <br>
                Kasir: <b>{{ $penjualan->kasir?->name ?? '-' }}</b>
                @if ($penjualan->cashierShift?->name)
                    ({{ $penjualan->cashierShift->name }})
                @endif
            </div>
            @if ($penjualan->refundPenjualans->isNotEmpty())
                <div style="margin-top:8px">
                    <span class="label label-warning">Retur / Ganti Barang</span>
                    @foreach ($penjualan->refundPenjualans as $refund)
                        <div style="font-size:12px;margin-top:3px">
                            {{ $refund->code }} &middot; {{ $refund->created_at?->format('d/m/Y H:i') }}
                            &middot; retur @currency($refund->returned_total ?? 0)
                            @if (($refund->replacement_total ?? 0) > 0)
                                &middot; pengganti @currency($refund->replacement_total)
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>