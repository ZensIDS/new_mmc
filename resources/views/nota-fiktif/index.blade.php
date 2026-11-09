@extends('layouts.master')

@section('title', 'Nota Fiktif')

@section('container')
    <section class="content-header">
        <h1>Nota Fiktif <small>riwayat</small></h1>
    </section>

    <section class="content">
        <div class="callout callout-warning">
            Nota di menu ini hanya arsip dokumen. Tidak mengurangi stok dan tidak masuk laporan apa pun.
        </div>

        <div class="box">
            <div class="box-header with-border">
                <form method="GET" class="form-inline">
                    <a href="{{ route('nota-fiktif.create') }}" class="btn bg-green"><i class="fa fa-plus"></i> Buat Nota</a>
                    &nbsp;
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari no. nota / customer / toko">
                    <input type="date" name="dari" value="{{ request('dari') }}" class="form-control" title="Dari tanggal">
                    <input type="date" name="sampai" value="{{ request('sampai') }}" class="form-control" title="Sampai tanggal">
                    <button class="btn btn-primary"><i class="fa fa-search"></i> Cari</button>
                    @if ($q !== '' || request('dari') || request('sampai'))
                        <a href="{{ route('nota-fiktif.index') }}" class="btn btn-default">Reset</a>
                    @endif
                </form>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-bordered table-striped table-hover" style="margin:0">
                    <thead>
                        <tr>
                            <th>No. Nota</th>
                            <th>Tanggal</th>
                            <th>Toko</th>
                            <th>Customer</th>
                            <th>Pembayaran</th>
                            <th class="text-right">Item</th>
                            <th class="text-right">Total</th>
                            <th style="width:210px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notas as $n)
                            <tr>
                                <td><b>{{ $n->code }}</b></td>
                                <td>{{ $n->tanggal->format('d/m/Y H:i') }}</td>
                                <td>{{ $n->outlet_nama }}</td>
                                <td>{{ $n->customer_nama ?: '—' }}</td>
                                <td>{{ $n->metode_bayar ?: '—' }}</td>
                                <td class="text-right">{{ $n->items_count }}</td>
                                <td class="text-right">@currency($n->total)</td>
                                <td>
                                    <a href="{{ route('nota-fiktif.show', $n) }}" class="btn btn-xs btn-info"><i class="fa fa-print"></i> Lihat/Cetak</a>
                                    <a href="{{ route('nota-fiktif.edit', $n) }}" class="btn btn-xs btn-warning"><i class="fa fa-pencil"></i> Edit</a>
                                    <form method="POST" action="{{ route('nota-fiktif.destroy', $n) }}" style="display:inline" onsubmit="return confirm('Hapus nota {{ $n->code }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted" style="padding:30px">Belum ada nota fiktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $notas->links() }}</div>
        </div>
    </section>
@endsection