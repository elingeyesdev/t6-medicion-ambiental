@extends('layouts.app')
@section('titulo', 'Revisión forestal')
@section('clase-cuerpo', 'pagina-revision-forestal')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/revision/forestal.css') }}">
@endpush

@section('contenido')
<div id="revision-forestal" class="rf-contenido" data-consulta="{{ route('revision.forestal.consulta') }}">
    <header class="rf-cabecera"><div><h1>Revisión forestal</h1></div></header>
    <p class="alert alert-warning rf-aviso"><strong>Revisión técnica. Método pendiente de aprobación.</strong></p>
    <p>Consulta la pérdida de cobertura arbórea en la ventana técnica del paquete. La pérdida no identifica por sí sola deforestación ni su causa.</p>
    <section class="card rf-panel" aria-labelledby="rf-titulo-consulta">
        <div class="card-body">
            <h2 id="rf-titulo-consulta" class="card-title">Paquete y años</h2>
            <form id="rf-formulario" action="{{ route('revision.forestal') }}" method="get">
                <div class="rf-filtros">
                    <div class="rf-id"><label class="form-label" for="rf-id">Código del paquete</label><input id="rf-id" class="form-control" name="id" type="text" pattern="[a-f0-9]{64}" minlength="64" maxlength="64" value="{{ $valores['id'] }}" required autocomplete="off" spellcheck="false" aria-describedby="rf-ayuda"></div>
                    <div><label class="form-label" for="rf-desde">Desde (año)</label><input id="rf-desde" class="form-control" name="desde" type="text" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" value="{{ $valores['desde'] }}" required></div>
                    <div><label class="form-label" for="rf-hasta">Hasta (año)</label><input id="rf-hasta" class="form-control" name="hasta" type="text" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" value="{{ $valores['hasta'] }}" required></div>
                    <button id="rf-consultar" class="btn btn-primary" type="submit">Consultar</button>
                </div>
                <p id="rf-ayuda" class="text-secondary mt-2 mb-0">Ambos años incluidos.</p>
            </form>
        </div>
    </section>
    <p id="rf-estado" class="alert alert-info" role="status" aria-live="polite">{{ $error ?? ($valores['id'] === '' ? 'No hay un paquete seleccionado. Abre el enlace con su código.' : 'Indica los años y pulsa Consultar para revisar el paquete.') }}</p>
    <noscript><p class="alert alert-warning">Activa JavaScript para consultar el paquete y utilizar el mapa.</p></noscript>
    <section id="rf-resultados" hidden aria-busy="false" aria-label="Resultado de la revisión">
        <h2 id="rf-periodo" class="h3"></h2>
        <p id="rf-alcance"></p>
        <dl id="rf-resumen" class="rf-indicadores"></dl>
        <div class="rf-rejilla">
            <section class="card rf-panel" aria-labelledby="rf-titulo-mapa">
                <div class="card-body rf-herramientas"><h3 id="rf-titulo-mapa" class="card-title">Pérdida de cobertura arbórea</h3><label for="rf-base" class="visually-hidden">Fondo del mapa</label><select id="rf-base" class="form-select" disabled><option value="claro">Claro</option><option value="relieve">Relieve</option><option value="satelite">Satélite</option><option value="calles">Calles</option></select><button id="rf-centrar" class="btn btn-outline-primary" type="button" disabled>Centrar capa</button></div>
                <div id="rf-mapa" tabindex="0" aria-label="Mapa de los polígonos de pérdida; usa la rueda para acercar o alejar"></div>
                <div class="card-body"><p id="rf-estado-mapa" role="status" hidden></p><p id="rf-seleccion">Selecciona un polígono en el mapa o en la lista para centrarlo.</p><h4 class="h4">Año de pérdida</h4><ul id="rf-leyenda" class="rf-leyenda" aria-label="Años y colores"></ul></div>
            </section>
            <section class="card rf-panel" aria-labelledby="rf-titulo-serie"><div class="card-body"><h3 id="rf-titulo-serie" class="card-title">Pérdida por año</h3><div class="rf-tabla"><table class="table"><thead><tr><th scope="col">Año</th><th scope="col">Pérdida (ha)</th></tr></thead><tbody id="rf-serie"></tbody></table></div><h4 class="h4">Polígonos del período</h4><ul id="rf-poligonos" class="rf-poligonos"></ul></div></section>
        </div>
        <section class="card rf-panel" aria-labelledby="rf-titulo-fuente"><div class="card-body"><h3 id="rf-titulo-fuente" class="card-title">Sobre este recorte</h3><dl id="rf-contexto" class="rf-datos"></dl><details><summary>Fuente y límites</summary><dl id="rf-fuente" class="rf-datos mt-3"></dl><ul id="rf-limites"></ul></details></div></section>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script type="module" src="{{ asset('js/revision/forestal-inicio.js') }}"></script>
@endpush
