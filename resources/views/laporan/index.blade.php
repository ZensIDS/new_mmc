@extends('layouts.master')
@section('title', 'Laporan Gudang')
@section('container')
    @include('laporan._index-layout', [
        'kind'     => 'gudang',
        'groups'   => $groups,
        'cashiers' => $cashiers,
        'outlets'  => $outlets,
        'rafaksis' => collect(),
        'notes'    => [],
    ])
@endsection

@section('page-script')
    @include('laporan._scripts')
@endsection