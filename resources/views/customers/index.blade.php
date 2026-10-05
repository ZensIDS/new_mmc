@extends('layouts.master')

@section('title', 'Customer')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Customer
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('customer.create') }}" class="btn btn-md bg-green">Tambah</a>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Nama</td>
                                    <td>Alamat</td>
                                    <td>No Telp</td>
                                    <td class="text-right">Jumlah Transaksi</td>
                                    <td class="text-right">Total Transaksi</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($users as $value)
                                @php
                                    $stat = $stats[(string) $value->id] ?? null;
                                    $jumlah = (int) ($stat->jumlah_transaksi ?? 0);
                                    $total = (float) ($stat->total_transaksi ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        {{ $value->name }}
                                        @if ($value->isDefaultCustomer())
                                            <span class="label label-primary">Default</span>
                                        @endif
                                    </td>
                                    <td>{{ $value->alamat }}</td>
                                    <td>{{ $value->no_telp }}</td>
                                    <td class="text-right" data-order="{{ $jumlah }}">{{ number_format($jumlah, 0, ',', '.') }}</td>
                                    <td class="text-right" data-order="{{ $total }}">@currency($total)</td>
                                    <td>
                                        <a class="btn btn-info" href="{{ route('customer.show', $value->id) }}">Show</a>
                                        @unless ($value->isDefaultCustomer())
                                            <a class="btn btn-warning" href="{{ route('customer.edit', $value->id) }}">Edit</a>
                                            <form action="{{ route('customer.destroy', $value->id) }}" method="post"
                                                style="display: inline;">
                                                @method('delete')
                                                @csrf
                                                <button class="border-0 btn btn-danger"
                                                    onclick="return confirm('Are you sure?')">Hapus</button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection