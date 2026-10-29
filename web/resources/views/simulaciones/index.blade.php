@extends('layouts.app')
@section('titulo', 'Simulaciones')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/simulaciones.css') }}">
@endpush
@section('contenido')
<div class="sim-contenido">
    <h1>Simulaciones</h1>
    <p class="sim-aviso">Prueba escenarios ambientales. Sus resultados son simulados, no observaciones ni pronósticos.</p>
    @include('cuentas.estado')
    @if($sesion)
        @include('simulaciones.resultado')
    @endif
    @include('simulaciones.formulario')
    <section class="card mt-4"><div class="card-header"><h2>Mis simulaciones</h2></div><div class="card-body">
        @forelse($sesiones as $guardada)
            <a class="sim-guardada" href="{{ route('simulaciones',['sesion'=>$guardada->id]) }}"><strong>{{ $guardada->nombre }}</strong><span>{{ $guardada->created_at->setTimezone('America/La_Paz')->format('d/m/Y H:i') }} · {{ $guardada->estado }}</span></a>
        @empty<p>Todavía no guardaste una simulación.</p>@endforelse
        {{ $sesiones->links() }}
    </div></section>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script id="catalogo-escenarios" type="application/json">@json($escenarios)</script>
<script id="reproduccion-simulacion" type="application/json">@json($reproduccion)</script>
<script type="module" src="{{ asset('js/simulaciones/inicio.js') }}"></script>
@endpush
