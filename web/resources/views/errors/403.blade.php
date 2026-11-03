@extends('layouts.publico')

@section('titulo', 'Acceso denegado')
@section('clase-publica', 'd-flex flex-column')

@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
@endpush

@section('contenido')
<main id="contenido" class="page page-center" tabindex="-1">
    <div class="container-tight py-4">
        <div class="empty">
            <p class="empty-header">403</p>
            <h1 class="empty-title">Acceso denegado</h1>
            <p class="empty-subtitle text-secondary">Tu cuenta no tiene permiso para acceder a esta página.</p>
            <div class="empty-action btn-list justify-content-center">
                <a href="{{ route('mapa') }}" class="btn btn-primary">Volver al mapa</a>
                <a href="{{ route('inicio') }}" class="btn">Volver al inicio</a>
            </div>
        </div>
    </div>
</main>
@endsection
