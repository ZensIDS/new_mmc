@extends('layouts.master')

@section('title', 'Edit Nota Fiktif')

@section('container')
    <section class="content-header">
        <h1>Edit Nota Fiktif <small>{{ $nota->code }}</small></h1>
    </section>
    <section class="content">
        @include('nota-fiktif._form', ['action' => route('nota-fiktif.update', $nota), 'method' => 'PUT'])
    </section>
@endsection