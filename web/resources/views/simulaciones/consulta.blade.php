@extends('layouts.app')
@section('titulo', 'Simulaciones')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/simulaciones.css') }}">
@endpush
@section('contenido')
<div class="sim-contenido">
    <header class="panel-sim-header"><div><h1>Panel de <em>Simulación</em></h1><p>Consulta el recorrido compartido. Los valores de esta sección son simulados.</p></div><a class="btn btn-outline-primary" href="{{ route('mapa') }}">Ver en el mapa</a></header>
    <p class="sim-aviso">Tu cuenta permite consultar. Los controles para crear o modificar recorridos corresponden a Analista y Administrador.</p>
    @include('simulaciones.continua')
</div>
@endsection
@push('scripts')
@include('cuentas.configuracion-unidades')
<script type="module" src="{{ asset('js/simulaciones/continua.js') }}"></script>
<script type="module" src="{{ asset('js/simulaciones/presentacion.js') }}"></script>
@endpush
