@extends('layouts.master')

@section('title', 'Detail Customer')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Transaksi Customer
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-4 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-aqua"><i class="fa fa-user"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Customer</span>
                        <span class="info-box-number" style="font-size:16px">{{ $customer->name }}</span>
                        <small>{{ $customer->no_telp ?: '-' }} {{ $customer->alamat ? '· ' . $customer->alamat : '' }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-green"><i class="fa fa-shopping-cart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Jumlah Transaksi</span>
                        <span class="info-box-number">{{ number_format($penjualan->count(), 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-12 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-yellow"><i class="fa fa-money"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Transaksi</span>
                        <span class="info-box-number">@currency($totalTransaksi)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body table-responsive text-nowrap">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Kode Invoice</th>
                                    <th>Outlet</th>
                                    <th>Kassa (akun)</th>
                                    <th>Nama kasir shift</th>
                                    <th>Detail</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($penjualan as $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td data-order="{{ $value->created_at?->timestamp }}">{{ $value->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $value->code }}</td>
                                    <td>{{ $value->outlet->name ?? '-' }}</td>
                                    <td>{{ $value->kasir->name ?? '-' }}</td>
                                    <td>{{ $value->cashierShift?->name ?? '—' }}</td>
                                    <td>
                                        <div class="table-responsive text-nowrap">
                                            <table class="table table-sm table-bordered">
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Banyak</th>
                                                    <th>Harga Jual</th>
                                                    <th>Sub total</th>
                                                </tr>
                                                @php $totalCost = 0; @endphp
                                                @foreach ($value->items as $item)
                                                    <tr>
                                                        <td>{{ $item->serial_number ? $item->serial_number : $item->product?->code }} - {{ $item->product?->name ?? '(produk dihapus)' }}</td>
                                                        <td>{{ $item->qty }}</td>
                                                        <td>@currency($item->price)</td>
                                                        <td>@currency($item->subtotal ?? ($item->qty * $item->price))</td>
                                                    </tr>
                                                    @php $totalCost += $item->subtotal ?? ($item->qty * $item->price); @endphp
                                                @endforeach
                                                <tr>
                                                    <th>Disc Toko : @currency($value->discount_total ?? $value->discount)</th>
                                                    <th>Promo : @currency($value->promotion_total ?? 0)</th>
                                                    <th>Voucher : @currency($value->voucher_total ?? 0)</th>
                                                    <th class="text-right">Subtotal : @currency($value->subtotal ?? $totalCost)</th>
                                                </tr>
                                                <tr>
                                                    <th colspan="4" class="text-right">Grand Total : @currency($value->final_total)</th>
                                                </tr>
                                                <tr>
                                                    <th colspan="3" class="text-right">Dibayar</th>
                                                    <th class="text-right">@currency($value->paid_amount ?? $value->total ?? 0)</th>
                                                </tr>
                                                <tr>
                                                    <th colspan="3" class="text-right">Kembalian</th>
                                                    <th class="text-right">@currency($value->change_amount ?? 0)</th>
                                                </tr>
                                            </table>
                                        </div>
                                    </td>
                                    <td>
                                        <a class="btn btn-info" href="{{ route('penjualan.show', $value->id) }}">Show</a>
                                        <a class="btn btn-warning" href="{{ route('penjualan.print', $value->id) }}">Re-Print</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        @if ($penjualan->isEmpty())
                            <div class="alert alert-info text-center" style="margin-top:15px;margin-bottom:0">
                                Customer ini belum memiliki transaksi.
                            </div>
                        @endif
                    </div><!-- /.box-body -->
                    <div class="box-footer">
                        <a href="{{ route('customer.index') }}" class="btn btn-default">Kembali</a>
                    </div>
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection