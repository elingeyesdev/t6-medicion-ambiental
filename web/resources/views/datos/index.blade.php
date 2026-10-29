@extends('layouts.app')
@section('titulo', 'Mediciones')
@section('clase-cuerpo', 'pagina-datos')

@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/datos.css') }}">
@endpush

@section('contenido')
<div class="datos-contenido">
    <h1>Mediciones ambientales</h1>
    @include('cuentas.estado')
    <x-datos.selector-unidades />
    @include('datos.filtros')
    @if($punto)
        @include('datos.consulta')
    @else
        <p class="datos-vacio">No hay puntos en esta selección.</p>
    @endif
    @can('datos.gestionar')
        @include('datos.operaciones')
    @endcan
</div>
@endsection

@push('scripts')
@include('cuentas.configuracion-unidades')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="application/json" id="datos-mediciones">@json($datosMapa)</script>
<script type="module" src="{{ asset('js/datos/mediciones.js') }}"></script>
@endpush
