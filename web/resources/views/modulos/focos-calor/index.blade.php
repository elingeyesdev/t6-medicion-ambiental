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
            <summary class="enviro-consulta-titulo"><x-panel.icono nombre="flame" /><h1>Focos de calor</h1><span class="enviro-plegar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg></span></summary>
            <div class="enviro-consulta-cuerpo">
                @include('modulos.focos-calor.filtros')
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
