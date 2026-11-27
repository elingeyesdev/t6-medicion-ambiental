@extends('layouts.app')
@section('titulo', 'Simulaciones')
@push('estilos')
@if($seccion === 'resultado')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/mapas/centrar-seleccion.css') }}">
@endif
<link rel="stylesheet" href="{{ asset('css/paginas/simulaciones.css') }}">
@endpush
@section('contenido')
<div class="sim-contenido">
    <header class="sim-workspace">
        <h1>Simulaciones</h1>
        <p>Explora escenarios ambientales con datos simulados.</p>
    </header>
    @include('cuentas.estado')
    <nav class="sim-workspace-nav" aria-label="Secciones de simulación">
        @foreach(['escenarios' => 'Crear escenario', 'continua' => 'Simulación en vivo', 'guardadas' => 'Mis simulaciones'] as $clave => $etiqueta)
            <a href="{{ route('simulaciones', $clave === 'escenarios' ? [] : ['seccion' => $clave]) }}" @if($seccion === $clave) aria-current="page" @endif>{{ $etiqueta }}</a>
        @endforeach
        @if($sesion)<span aria-current="page">Resultado</span>@endif
    </nav>
    @if($seccion === 'escenarios')
        @include('simulaciones.formulario')
    @elseif($seccion === 'continua')
        @include('simulaciones.continua')
    @elseif($seccion === 'resultado')
        @include('simulaciones.resultado')
    @else
    <section class="sim-table-card" id="sim-guardadas" aria-labelledby="sim-guardadas-titulo">
        <div class="sim-section-header"><div><p class="sim-eyebrow">Escenarios guardados</p><h2 id="sim-guardadas-titulo">Mis <em>simulaciones</em></h2></div><span class="sim-page-tag">{{ $sesiones->total() }} en total</span></div>
        <div class="sim-guardadas">
        @forelse($sesiones as $guardada)
            <a class="sim-guardada" href="{{ route('simulaciones',['sesion'=>$guardada->id]) }}"><span><strong>{{ $guardada->nombre }}</strong><small>{{ $guardada->created_at->setTimezone('America/La_Paz')->format('d/m/Y H:i') }} · Bolivia</small></span><span class="sim-guardada-estado">{{ $guardada->estado }} <span aria-hidden="true">↗</span></span></a>
        @empty<p class="sim-vacio">Todavía no guardaste una simulación. Configura un escenario por zona para comenzar.</p>@endforelse
        {{ $sesiones->links() }}
    </div></section>
    @endif
</div>
@endsection
@push('scripts')
@if($seccion === 'resultado' || $seccion === 'continua')
@include('cuentas.configuracion-unidades')
@endif
@if($seccion === 'resultado')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/mapas/centrar-seleccion.js') }}?v=2"></script>
@endif
@if($seccion === 'escenarios' || $seccion === 'resultado')
<script id="catalogo-escenarios" type="application/json">@json($escenarios)</script>
<script id="reproduccion-simulacion" type="application/json">@json($reproduccion)</script>
<script type="module" src="{{ asset('js/simulaciones/inicio.js') }}?v=controles-2"></script>
@endif
@if(in_array($seccion, ['escenarios', 'continua'], true))
<script type="module" src="{{ asset('js/simulaciones/presentacion.js') }}?v=secciones"></script>
@endif
@if($seccion === 'continua')
<script type="module" src="{{ asset('js/simulaciones/continua.js') }}"></script>
@endif
@endpush
