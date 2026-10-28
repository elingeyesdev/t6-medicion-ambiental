@extends('layouts.app')
@section('titulo', !empty($esInicio) ? 'Mapa de Monitoreo' : 'Focos de calor')
@section('clase-cuerpo', 'pagina-focos')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/modulos/focos-calor.css') }}">
<link rel="stylesheet" href="{{ asset('css/modulos/mapa-monitoreo.css') }}">
@endpush

@section('contenido')
<div id="visor-focos" data-api="{{ url('/api/v1') }}">
    @include('modulos.focos-calor.mapa')
    <div class="enviro-controles">
        <details class="enviro-consulta" id="controles-mapa" open>
            <summary class="enviro-consulta-titulo"><x-panel.icono nombre="flame" /><span><small>Monitoreo ambiental</small><h1>Focos de calor</h1></span><span class="enviro-plegar" aria-hidden="true">⌃</span></summary>
            <div class="enviro-consulta-cuerpo">
                <span class="fuente-insignia">NASA FIRMS · VIIRS</span>
                @include('modulos.focos-calor.filtros')
                <p class="nota-interpretacion">Una detección térmica <strong>no confirma un incendio</strong>.</p>
            </div>
        </details>
        @include('modulos.focos-calor.estado')
    </div>
    @include('modulos.focos-calor.resultados')
    <noscript><p class="estado-consulta aviso-sin-js">Esta pantalla interactiva requiere JavaScript. Puedes consultar el <a href="{{ url('/api/v1/territorios') }}">catálogo en formato JSON</a>. No se muestran resultados ambientales sin ejecutar la consulta.</p></noscript>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script type="module" src="{{ asset('js/focos-calor/inicio.js') }}"></script>
@endpush
