@extends('layouts.app')
@section('titulo', 'Cobertura forestal')
@section('clase-cuerpo', 'pagina-cobertura-forestal')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/mapas/centrar-seleccion.css') }}">
<link rel="stylesheet" href="{{ asset('css/mapas/forestal.css') }}?v=linea-1">
@endpush

@section('contenido')
<section class="forestal-publicado" id="forestal-publicado"
    data-limite="{{ url('/api/v1/territorios/BO07/limite') }}"
    data-version="{{ $capa['version'] }}" data-copa="{{ $capa['umbral_copa'] }}">
    <header>
        <h1>Cobertura forestal</h1>
        <p>Pérdida de cobertura arbórea observada en Santa Cruz, Bolivia.</p>
    </header>
    <section class="card">
        <div class="card-body forestal-controles">
            <form id="forestal-periodo">
                <div><label for="forestal-desde" class="form-label">Desde</label>
                    <input class="form-control" type="number" id="forestal-desde" min="{{ $capa['primer_anio'] }}" max="{{ $capa['ultimo_anio'] }}" value="{{ $capa['ultimo_anio'] }}" required></div>
                <div><label for="forestal-hasta" class="form-label">Hasta</label>
                    <input class="form-control" type="number" id="forestal-hasta" min="{{ $capa['primer_anio'] }}" max="{{ $capa['ultimo_anio'] }}" value="{{ $capa['ultimo_anio'] }}" required></div>
                <button class="btn btn-primary" type="submit">Consultar</button>
            </form>
            <div class="forestal-mapa-controles">
                <label for="forestal-base" class="visually-hidden">Fondo del mapa</label>
                <select id="forestal-base" class="form-select" disabled><option value="claro">Mapa claro</option><option value="relieve">Relieve</option><option value="satelite">Satélite</option><option value="calles">Calles</option></select>
                <button class="btn btn-outline-primary" id="forestal-centrar" type="button" disabled>Centrar Santa Cruz</button>
            </div>
        </div>
        <div class="forestal-linea">
            <label for="forestal-linea">Explorar por año</label>
            <output id="forestal-anio-elegido" for="forestal-linea">{{ $capa['ultimo_anio'] }}</output>
            <input type="range" id="forestal-linea" min="{{ $capa['primer_anio'] }}" max="{{ $capa['ultimo_anio'] }}" value="{{ $capa['ultimo_anio'] }}" disabled>
            <p>El satélite publica la pérdida por año. Cada posición muestra las observaciones de ese año.</p>
        </div>
        <div id="forestal-mapa" aria-label="Mapa de pérdida de cobertura arbórea en Santa Cruz"></div>
        <div class="card-body forestal-contexto">
            <p id="forestal-estado" role="status" aria-live="polite">Cargando los límites de Santa Cruz…</p>
            <p id="forestal-leyenda" hidden><span class="forestal-muestra" aria-hidden="true"></span> Pérdida observada <strong id="forestal-anios"></strong></p>
            <p id="forestal-aviso-base" role="status" hidden></p>
            <p class="mb-0">Fuente <a href="https://globalnaturewatch.org/help/datasets/forest-change/" target="_blank" rel="noopener noreferrer">Hansen GFC {{ $capa['version'] }} · Global Forest Watch</a>. Copa arbórea en 2000 ≥ {{ $capa['umbral_copa'] }} %. La pérdida de cobertura puede tener distintas causas. Este mapa no confirma deforestación ni su causa.</p>
        </div>
    </section>
</section>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script src="{{ asset('js/mapas/centrar-seleccion.js') }}?v=2" defer></script>
<script type="module" src="{{ asset('js/mapas/forestal.js') }}?v=linea-1"></script>
@endpush
