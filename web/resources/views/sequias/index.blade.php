@extends('layouts.app')
@section('titulo', 'Sequías')
@section('clase-cuerpo', 'pagina-sequias')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/sequias.css') }}">
@endpush

@section('contenido')
<div id="visor-sequias" data-api="{{ url('/api/v1') }}">
    <div class="sq-intro"><div><p class="etiqueta">SANTA CRUZ · INDICADOR METEOROLÓGICO</p><h1>Sequías</h1><p>La lluvia en contexto, a escala de una celda.</p></div><span class="sq-fuente">ERA5 · SPI-3</span></div>
    <p class="sq-interpretacion">Índice de precipitación de tres meses frente a <strong>1991–2020</strong>. Derivado de reanálisis: no es una estación ni confirma daños por sequía.</p>

    <section class="sq-panel sq-filtros" aria-labelledby="sq-titulo-filtros">
        <div class="sq-encabezado"><h2 id="sq-titulo-filtros">Define tu consulta</h2><span>Meses completos · calendario UTC</span></div>
        <form id="sq-formulario">
            <div class="sq-rejilla-filtros">
                <label>Provincia<select name="provincia" id="sq-provincia"><option value="">Todo Santa Cruz</option></select></label>
                <label>Municipio<select name="municipio" id="sq-municipio"><option value="">Todos los municipios</option></select></label>
                <label>Muestra<select name="muestra" id="sq-muestra"><option value="">Todas las muestras del territorio</option></select></label>
                <label>Mes final<input id="sq-mes" name="mesFinal" type="month" min="2021-01" max="{{ $catalogos['mesMaximo'] }}" value="{{ $catalogos['mesMaximo'] }}" required></label>
                <div class="sq-acciones"><button class="sq-boton" type="submit">Consultar sequías</button><button class="sq-secundario" type="button" id="sq-restaurar" hidden>Restaurar filtros aplicados</button></div>
            </div>
            <p class="sq-ayuda">Se incluyen el mes elegido y los dos anteriores. Solo se consultan resultados preparados; un mes sin publicación se informa como no disponible.</p>
        </form>
        <a class="sq-enlace" href="{{ route('modulos.mostrar', 'sequias') }}?mesFinal=2024-09&amp;municipio=BO070101&amp;muestra=scz-piloto-01">Ver piloto histórico · julio–septiembre de 2024 →</a>
    </section>

    <div class="sq-estado" id="sq-estado" role="status" aria-live="polite"><strong id="sq-titulo-estado">Elige territorio y mes</strong><p id="sq-mensaje-estado">Los resultados conservan su período y fuente. Un valor ausente nunca se interpreta como normal.</p><button type="button" class="sq-secundario" id="sq-reintentar" hidden>Reintentar consulta</button></div>
    <noscript><p class="sq-estado">Activa JavaScript para usar el mapa y los filtros. También puedes consultar la <a class="sq-enlace" href="{{ url('/api/v1/sequias') }}?mesFinal=2024-09">API de sequías en JSON</a>.</p></noscript>

    <section id="sq-resultados" hidden aria-labelledby="sq-titulo-resultados" aria-busy="false">
        <div class="sq-contexto"><div><span class="etiqueta" id="sq-vigencia">CONSULTA APLICADA</span><h2 id="sq-titulo-resultados" tabindex="-1"></h2><p id="sq-periodo"></p></div><div class="sq-obtencion" id="sq-obtencion"></div></div>
        <div class="sq-indicadores">
            <article><span>Muestras publicadas</span><strong id="sq-total">—</strong><small id="sq-seleccionadas"></small></article>
            <article><span>SPI mínimo de las muestras</span><strong id="sq-minimo">—</strong><small>Adimensional · no es un promedio</small></article>
            <article><span>Publicadas sin índice</span><strong id="sq-sin-indice">—</strong><small>Se conserva el motivo del faltante</small></article>
            <article><span>Muestras por preparar</span><strong id="sq-pendientes">—</strong><small>Sin datos publicados para este mes</small></article>
        </div>
        <p class="sq-cobertura" id="sq-cobertura"></p>
        <div class="sq-rejilla-visor">
            <section class="sq-panel" aria-labelledby="sq-titulo-mapa">
                <div class="sq-encabezado"><h3 id="sq-titulo-mapa">Puntos de celda, no superficie afectada</h3><button class="sq-texto" type="button" id="sq-centrar">Centrar</button></div>
                <div id="sq-mapa" aria-label="Mapa de las celdas publicadas de ERA5" tabindex="-1"></div>
                <p id="sq-error-mapa" class="sq-ayuda" role="status" hidden></p>
                <div class="sq-leyenda"><span><i style="--color:#9a382d"></i>Extremadamente seco</span><span><i style="--color:#bc673b"></i>Muy seco</span><span><i style="--color:#bd9842"></i>Moderadamente seco</span><span><i style="--color:#547d6e"></i>Normal</span><span><i style="--color:#3c7c99"></i>Húmedo*</span><span><i style="--color:#7d8183"></i>Sin índice</span></div>
                <p class="sq-ayuda">*Las categorías húmedas se distinguen por su nombre en la ficha. El contorno solo da contexto; no se rellena ni se estima área afectada. Cada punto usa el centro de celda, no la coordenada solicitada.</p>
            </section>
            <section class="sq-panel" aria-labelledby="sq-titulo-lista"><div class="sq-encabezado"><h3 id="sq-titulo-lista">Muestras publicadas</h3><span id="sq-conteo"></span></div><div id="sq-lista"></div></section>
        </div>
        <section class="sq-panel sq-detalle" id="sq-detalle" hidden aria-labelledby="sq-titulo-detalle"><div class="sq-encabezado"><h3 id="sq-titulo-detalle" tabindex="-1">Detalle de la muestra</h3><button class="sq-secundario" type="button" id="sq-cerrar">Volver a resultados</button></div><dl class="sq-datos" id="sq-campos-detalle"></dl><p class="sq-cobertura" id="sq-motivo-detalle" hidden></p></section>
        <details class="sq-panel sq-procedencia"><summary>Fuente, método y límites de interpretación</summary><div id="sq-fuentes"></div><ul id="sq-advertencias"></ul></details>
        <details class="sq-panel sq-procedencia" id="sq-listado-pendientes" hidden><summary id="sq-titulo-pendientes">Muestras sin preparar</summary><p class="sq-ayuda">No se les asigna SPI ni categoría. Estar registradas no acredita datos climáticos disponibles.</p><ul id="sq-nombres-pendientes"></ul></details>
    </section>
</div>
<script id="sq-catalogos" type="application/json">{!! json_encode($catalogos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script type="module" src="{{ asset('js/sequias/inicio.js') }}"></script>
@endpush
