@extends('layouts.app')
@section('titulo', 'Simulaciones')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/simulaciones.css') }}">
@endpush
@section('contenido')
<div class="sim-contenido">
    <header class="panel-sim-header">
        <div>
            <p class="panel-sim-eyebrow">Consola de simulación · <span data-consola-paso>Tiempo real y escenarios</span></p>
            <h1 class="panel-sim-title">Panel de <em>simulación</em></h1>
            <p class="panel-sim-subtitle">Genera lecturas sintéticas para visualizar escenarios ambientales y comparar zonas.</p>
        </div>
        <span class="sim-page-tag">Operación</span>
    </header>
    @include('cuentas.estado')
    <nav class="sim-secciones" aria-label="Secciones de simulación">
        <a href="#sim-tiempo-real">Tiempo real</a>
        <a href="#nueva-simulacion">Escenarios por zona</a>
        @if($sesion)<a href="#sim-resultado">Simulación actual</a>@endif
        <a href="#sim-guardadas">Mis simulaciones <span>{{ $sesiones->total() }}</span></a>
    </nav>
    <p class="sim-aviso">Datos simulados · Los resultados son pruebas de escenarios, no observaciones ni pronósticos.</p>
    @include('simulaciones.continua')
    @if($sesion)
        @include('simulaciones.resultado')
    @endif
    @include('simulaciones.formulario')
    <section class="sim-table-card" id="sim-guardadas" aria-labelledby="sim-guardadas-titulo">
        <div class="sim-section-header"><div><p class="sim-eyebrow">Escenarios guardados</p><h2 id="sim-guardadas-titulo">Mis <em>simulaciones</em></h2></div><span class="sim-page-tag">{{ $sesiones->total() }} en total</span></div>
        <div class="sim-guardadas">
        @forelse($sesiones as $guardada)
            <a class="sim-guardada" href="{{ route('simulaciones',['sesion'=>$guardada->id]) }}"><span><strong>{{ $guardada->nombre }}</strong><small>{{ $guardada->created_at->setTimezone('America/La_Paz')->format('d/m/Y H:i') }} · Bolivia</small></span><span class="sim-guardada-estado">{{ $guardada->estado }} <span aria-hidden="true">↗</span></span></a>
        @empty<p class="sim-vacio">Todavía no guardaste una simulación. Configura un escenario por zona para comenzar.</p>@endforelse
        {{ $sesiones->links() }}
    </div></section>
</div>
@endsection
@push('scripts')
@include('cuentas.configuracion-unidades')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script id="catalogo-escenarios" type="application/json">@json($escenarios)</script>
<script id="reproduccion-simulacion" type="application/json">@json($reproduccion)</script>
<script type="module" src="{{ asset('js/simulaciones/presentacion.js') }}"></script>
<script type="module" src="{{ asset('js/simulaciones/inicio.js') }}"></script>
<script type="module" src="{{ asset('js/simulaciones/continua.js') }}"></script>
@endpush
