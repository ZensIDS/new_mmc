@extends('layouts.master')

@section('title', 'Buat Nota Fiktif')

@section('container')
    <section class="content-header">
        <h1>Buat Nota Fiktif</h1>
    </section>
    <section class="content">
        @include('nota-fiktif._form', ['action' => route('nota-fiktif.store'), 'method' => 'POST'])
    </section>
@endsection