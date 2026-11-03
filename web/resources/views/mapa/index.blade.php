@extends('layouts.app')
@section('titulo', 'Mapa de Monitoreo')
@section('clase-cuerpo', 'pagina-monitoreo')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/monitoreo.css') }}">
@endpush

@section('contenido')
<div class="monitoreo-contenedor">
    <aside class="monitoreo-consulta" aria-label="Consulta ambiental">
        <details id="filtros-monitoreo" open>
            <summary><h1>Navegación</h1><span aria-hidden="true">⌄</span></summary>
            <form id="consulta-monitoreo">
                <div>
                    <label class="form-label" for="mon-pais">País o territorio</label>
                    <select id="mon-pais" name="pais" class="form-select">
                        <option value="">Sudamérica</option>
                        @foreach($paises as $pais)<option value="{{ $pais['codigo'] }}">{{ $pais['nombre'] }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="mon-region">Región</label>
                    <select id="mon-region" name="region" class="form-select"><option value="">Todas las regiones</option></select>
                </div>
                <details id="periodo-monitoreo" class="monitoreo-periodo">
                <summary>Período y variable</summary>
                <div class="monitoreo-periodo-campos">
                <div>
                    <label class="form-label" for="mon-metrica">Variable</label>
                    <select id="mon-metrica" name="metrica" class="form-select">
                        @foreach($metricas as $clave => $metrica)<option value="{{ $clave }}">{{ $metrica['nombre'] }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="mon-origen">Datos</label>
                    <select id="mon-origen" name="origen" class="form-select">
                        <option value="">Registros y estimaciones</option>
                        <option value="observado">Solo registros</option>
                        <option value="estimado">Solo estimaciones</option>
                        <option value="simulado">Solo simulados</option>
                        <option value="todos">Todos, incluidos los simulados</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="mon-desde">Desde</label>
                    <input id="mon-desde" name="desde" class="form-control" type="datetime-local" value="{{ now('America/La_Paz')->subDays(7)->format('Y-m-d\TH:i') }}" max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}" required>
                </div>
                <div>
                    <label class="form-label" for="mon-hasta">Hasta</label>
                    <input id="mon-hasta" name="hasta" class="form-control" type="datetime-local" value="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}" max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}" required>
                </div>
                <p>Hasta siete días · Hora de Bolivia.</p>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="por_momento"><span class="form-check-label">Ver por momento</span></label>
                <div id="recorrido-mapa" class="recorrido-mapa" hidden>
                    <label for="recorrido-momento" class="form-label">Momento de la consulta</label>
                    <input id="recorrido-momento" name="momento" type="datetime-local" class="form-control" disabled>
                    <label for="recorrido-barra" class="form-label">Recorrer el período</label>
                    <input id="recorrido-barra" type="range" step="1" class="form-range">
                    <div class="buscador-acciones"><button id="recorrido-anterior" type="button" class="btn btn-outline-primary">Una hora antes</button><button id="recorrido-siguiente" type="button" class="btn btn-outline-primary">Una hora después</button></div>
                </div>
                </div>
                </details>
                <button class="btn btn-primary" type="submit">Ir a destino</button>
            </form>
        </details>
        <section id="ajustes-monitoreo" class="monitoreo-ajustes" aria-label="Ajustes del mapa" hidden>
        <header><h2>Ajustes del mapa</h2><button type="button" data-cerrar-ajustes aria-label="Cerrar ajustes">×</button></header>
        <nav class="monitoreo-pestanas" aria-label="Opciones del mapa">
            <button type="button" data-opcion-mapa="capas" aria-pressed="true">Capas</button>
            <button type="button" data-opcion-mapa="preferencias" aria-pressed="false">Preferencias</button>
        </nav>
        <div data-contenido-mapa="capas">
        @include('simulaciones.continua', ['compacto' => true])
        @include('components.mapas.capas')
        </div>
        <div data-contenido-mapa="preferencias" hidden>
        <details class="monitoreo-unidades">
            <summary>Unidades de medida</summary>
            @include('cuentas.unidades')
        </details>
        <details class="monitoreo-unidades" open><summary>Fondo del mapa</summary><div class="monitoreo-fondo-opciones">
            <label class="visually-hidden" for="mon-base">Fondo del mapa</label>
            <select id="mon-base" class="form-select" disabled><option value="claro">Claro</option><option value="relieve">Relieve</option><option value="satelite">Satélite</option><option value="calles">Calles</option></select>
            <button id="mon-centrar" class="btn btn-outline-primary" type="button">Centrar puntos</button>
        </div></details>
        <section class="monitoreo-buscador" aria-label="Buscar lugar">
            <form id="buscar-lugar" role="search">
                <label for="nombre-lugar" class="form-label">Buscar lugar</label>
                <input id="nombre-lugar" name="texto" class="form-control" type="search" placeholder="País, ciudad o lugar" minlength="2" maxlength="120" required autocomplete="off">
                <div class="buscador-acciones"><button type="submit" class="btn btn-primary">Buscar</button><button type="reset" class="btn btn-outline-primary">Limpiar</button></div>
            </form>
            <p id="estado-buscador" role="status" aria-live="polite"></p>
            <ul id="lugares-encontrados" aria-label="Lugares encontrados"></ul>
            <a class="buscador-fuente" href="https://www.mapbox.com/about/maps/" target="_blank" rel="noopener noreferrer">Mapbox</a>
        </section>
        <button id="mon-tiempo" type="button" class="btn btn-outline-primary monitoreo-tiempo" aria-pressed="false">Tiempo de un lugar</button>
        </div>
        </section>
        <section id="comparacion-fijada" class="comparacion-fijada" hidden aria-label="Referencia de comparación">
            <strong id="comparacion-nombre"></strong><p>Elige otro punto o momento para comparar.</p>
            <button id="quitar-comparacion" type="button" class="btn btn-outline-primary">Quitar comparación</button>
        </section>
        <p id="estado-monitoreo" class="monitoreo-estado" role="status" aria-live="polite">Cargando…</p>
        <div id="paginacion-monitoreo" class="monitoreo-paginacion" hidden>
            <button id="mon-anterior" class="btn btn-outline-primary" type="button">Anterior</button>
            <button id="mon-siguiente" class="btn btn-outline-primary" type="button">Siguiente</button>
        </div>
        <p class="monitoreo-aclaracion">Un punto de referencia no representa necesariamente una estación física.</p>
        <div class="monitoreo-barra" aria-label="Herramientas de monitoreo">
            <button type="button" id="abrir-ajustes-mapa" aria-label="Ajustes del mapa" title="Ajustes del mapa" aria-expanded="false" aria-controls="ajustes-monitoreo"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m10 3-1 3-3 1-2 3 2 2v3l3 2 1 3h4l1-3 3-2v-3l2-2-2-3-3-1-1-3Z"/><circle cx="12" cy="12" r="3"/></svg></button>
            <button type="button" id="abrir-historial-mapa" aria-label="Período e historial" title="Período e historial"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></button>
            <button type="button" id="abrir-buscador-mapa" aria-label="Buscar un lugar" title="Buscar un lugar"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/></svg></button>
            <a href="{{ route('simulaciones') }}" aria-label="Abrir Simulaciones" title="Abrir Simulaciones"><x-panel.icono nombre="flask" /></a>
        </div>
    </aside>
    <section class="monitoreo-escena" aria-label="Mapa ambiental">
        <div id="mapa-monitoreo" tabindex="0" role="region" aria-label="Puntos de monitoreo ambiental"></div>
        <div class="monitoreo-leyenda"><span class="mon-punto con-datos" aria-hidden="true"></span>Con datos<span class="mon-punto" aria-hidden="true"></span>Sin datos del período</div>
        <p id="mon-fuente-limite" class="monitoreo-procedencia" hidden></p>
        <aside id="mon-detalle" class="card monitoreo-detalle" aria-label="Punto seleccionado" hidden>
            <div class="card-header"><h2 id="mon-nombre"></h2><button id="mon-cerrar" class="btn btn-outline-primary" type="button" aria-label="Cerrar punto seleccionado">×</button></div>
            <div class="card-body" id="mon-informacion"></div>
        </aside>
    </section>
</div>
<noscript><p>Activa JavaScript para consultar el mapa interactivo. También puedes abrir el <a href="{{ route('sensores') }}">historial de mediciones</a>.</p></noscript>
@endsection

@push('scripts')
@include('cuentas.configuracion-unidades')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="application/json" id="configuracion-monitoreo">@json($configuracionMonitoreo)</script>
<script type="module" src="{{ asset('js/mapas/monitoreo.js') }}"></script>
<script src="{{ asset('js/mapas/interfaz-original.js') }}" defer></script>
@endpush
