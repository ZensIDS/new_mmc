@extends('layouts.master')
@section('title', 'Laporan Outlet')
@section('container')
    @include('laporan._index-layout', [
        'kind'     => 'outlet',
        'groups'   => $groups,
        'cashiers' => $cashiers,
        'outlets'  => $outlets,
        'rafaksis' => $rafaksis,
        'notes'    => [
            'Penjualan yang diretur tidak dihitung sebagai penjualan; barang pengganti tetap dihitung sebagai penjualan.',
            \App\Support\LaporanCatalog::showPdf()
                ? 'Semua laporan dapat dipratinjau sebagai PDF atau diunduh sebagai Excel (.xlsx).'
                : 'Semua laporan diunduh sebagai Excel (.xlsx).',
        ],
    ])
@endsection

@section('page-script')
    @include('laporan._scripts')
@endsection