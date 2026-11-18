@extends('layouts.master')

@section('title', 'Mutasi Saldo - ' . $customer->name)

@php
    $fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    // '' (bukan null) supaya periode "Semua" ikut terbawa ke print & Excel.
    $query = ['tanggal_mulai' => $mulai ?? '', 'tanggal_selesai' => $selesai ?? ''];
    $bulanIni = [now()->startOfMonth()->toDateString(), now()->toDateString()];
@endphp

@section('container')
    <style>
        .ms-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 18px 22px; margin-bottom: 16px; }
        .ms-name { font-size: 20px; font-weight: 700; margin: 0; }
        .ms-sub { color: #7a8594; margin: 2px 0 0; }
        .ms-now { text-align: right; }
        .ms-now small { display: block; color: #7a8594; }
        .ms-now b { font-size: 24px; color: #00a65a; }
        .ms-stat { border-radius: 12px; padding: 14px 18px; margin-bottom: 16px; color: #fff; }
        .ms-stat small { display: block; opacity: .9; }
        .ms-stat b { font-size: 20px; }
        .ms-stat.s-awal { background: linear-gradient(135deg, #5b6b82, #7f8da3); }
        .ms-stat.s-in { background: linear-gradient(135deg, #00a65a, #1ec98b); }
        .ms-stat.s-out { background: linear-gradient(135deg, #e08e0b, #f6b94c); }
        .ms-stat.s-akhir { background: linear-gradient(135deg, #3c8dbc, #5fb0e0); }
        .ms-table th { background: #f4f6f9; white-space: nowrap; }
        .ms-table tr.ms-edge td { background: #f7f9fb; font-weight: 700; }
        .ms-in { color: #00a65a; font-weight: 600; }
        .ms-out { color: #e08e0b; font-weight: 600; }
    </style>

    <section class="content-header">
        <h1>Mutasi Saldo <small>{{ $customer->name }}</small></h1>
    </section>

    <section class="content">
        <div class="ms-card">
            <div class="row">
                <div class="col-sm-7">
                    <p class="ms-name">{{ $customer->name }}</p>
                    <p class="ms-sub">{{ $customer->no_telp ?: '—' }}@if ($customer->alamat) · {{ $customer->alamat }}@endif</p>
                    <div style="margin-top:12px">
                        <a href="{{ route('saldo-customer.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Daftar</a>
                        <a href="{{ route('saldo-customer.topup', $customer) }}" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> Top Up</a>
                        <a href="{{ route('saldo-customer.kurangi', $customer) }}" class="btn btn-warning btn-sm"><i class="fa fa-minus"></i> Kurangi</a>
                    </div>
                </div>
                <div class="col-sm-5 ms-now">
                    <small>Saldo saat ini</small>
                    <b>{{ $fmt($saldoTerkini) }}</b>
                </div>
            </div>
        </div>

        <div class="ms-card">
            <form method="GET" class="form-inline">
                <label>Periode</label>
                <input type="date" name="tanggal_mulai" value="{{ $mulai }}" class="form-control">
                <span>s.d.</span>
                <input type="date" name="tanggal_selesai" value="{{ $selesai }}" class="form-control">
                <button class="btn btn-primary"><i class="fa fa-filter"></i> Terapkan</button>
                <a href="{{ route('saldo-customer.mutasi', [$customer, 'tanggal_mulai' => $bulanIni[0], 'tanggal_selesai' => $bulanIni[1]]) }}" class="btn btn-default">Bulan ini</a>
                <a href="{{ route('saldo-customer.mutasi', [$customer, 'tanggal_mulai' => '', 'tanggal_selesai' => '']) }}" class="btn btn-default">Semua</a>

                <span class="pull-right">
                    <a href="{{ route('saldo-customer.mutasi.print', [$customer] + $query) }}" target="_blank" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                    <a href="{{ route('saldo-customer.mutasi.export', [$customer] + $query) }}" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Excel</a>
                </span>
            </form>
        </div>

        <div class="row">
            <div class="col-sm-3 col-xs-6"><div class="ms-stat s-awal"><small>Saldo Awal</small><b>{{ $fmt($stmt['saldo_awal']) }}</b></div></div>
            <div class="col-sm-3 col-xs-6"><div class="ms-stat s-in"><small>Total Top Up</small><b>{{ $fmt($stmt['total_topup']) }}</b></div></div>
            <div class="col-sm-3 col-xs-6"><div class="ms-stat s-out"><small>Total Pengurangan</small><b>{{ $fmt($stmt['total_pengurangan']) }}</b></div></div>
            <div class="col-sm-3 col-xs-6"><div class="ms-stat s-akhir"><small>Saldo Akhir</small><b>{{ $fmt($stmt['saldo_akhir']) }}</b></div></div>
        </div>

        <div class="ms-card" style="padding:0;overflow:hidden">
            <div class="table-responsive">
                <table class="table table-bordered table-hover ms-table" style="margin:0">
                    <thead>
                        <tr>
                            <th style="width:110px">Tanggal</th>
                            <th>Keterangan</th>
                            <th class="text-right" style="width:160px">Top Up (masuk)</th>
                            <th class="text-right" style="width:170px">Pengurangan (keluar)</th>
                            <th class="text-right" style="width:160px">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="ms-edge">
                            <td>{{ $mulai ? \Carbon\Carbon::parse($mulai)->format('d/m/Y') : '' }}</td>
                            <td>Saldo Awal</td>
                            <td></td>
                            <td></td>
                            <td class="text-right">{{ $fmt($stmt['saldo_awal']) }}</td>
                        </tr>

                        @forelse ($stmt['rows'] as $r)
                            <tr>
                                <td>{{ $r['tanggal']->format('d/m/Y') }}</td>
                                <td>
                                    {{ $r['note'] !== '' ? $r['note'] : '—' }}
                                    @if ($r['petugas'])<br><small class="text-muted">oleh {{ $r['petugas'] }}</small>@endif
                                </td>
                                <td class="text-right ms-in">{{ $r['topup'] !== null ? $fmt($r['topup']) : '' }}</td>
                                <td class="text-right ms-out">{{ $r['pengurangan'] !== null ? $fmt($r['pengurangan']) : '' }}</td>
                                <td class="text-right">{{ $fmt($r['saldo']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted" style="padding:24px">Tidak ada mutasi pada periode ini.</td></tr>
                        @endforelse

                        <tr class="ms-edge">
                            <td>{{ $selesai ? \Carbon\Carbon::parse($selesai)->format('d/m/Y') : '' }}</td>
                            <td>Saldo Akhir</td>
                            <td class="text-right ms-in">{{ $fmt($stmt['total_topup']) }}</td>
                            <td class="text-right ms-out">{{ $fmt($stmt['total_pengurangan']) }}</td>
                            <td class="text-right">{{ $fmt($stmt['saldo_akhir']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-muted small">Saldo hanya catatan, tidak dipakai di kasir. Mutasi yang sudah tersimpan tidak bisa diubah atau dihapus; koreksi dengan entri baru.</p>
    </section>
@endsection