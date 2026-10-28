@extends('layouts.app')
@section('titulo', 'Focos de calor')
@section('clase-cuerpo', 'pagina-focos')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/focos-calor.css') }}">
@endpush

@section('contenido')
<div id="visor-focos" data-api="{{ url('/api/v1') }}">
    <div class="intro-focos">
        <div><p class="etiqueta">SANTA CRUZ · CONSULTA AMBIENTAL</p><h1>Focos de calor</h1><p>Una mirada al territorio, con datos y contexto.</p></div>
        <span class="fuente-insignia"><span aria-hidden="true">●</span> NASA FIRMS · VIIRS</span>
    </div>
    <p class="nota-interpretacion">Una detección térmica <strong>no confirma un incendio</strong>. La confianza no expresa gravedad; la potencia radiativa (MW) no es superficie quemada.</p>

    <section class="panel-filtros" aria-labelledby="titulo-filtros">
        <div class="encabezado-panel"><h2 id="titulo-filtros">Define tu consulta</h2><span>Hora de Bolivia · UTC−4</span></div>
        <form id="filtros-focos">
            <fieldset id="campos-filtros" disabled>
                <legend class="solo-lector">Territorio, período y confianza</legend>
                <div class="rejilla-filtros">
                    <label>Provincia<select name="provincia" id="provincia"><option value="">Todo Santa Cruz</option></select></label>
                    <label>Municipio<select name="municipio" id="municipio"><option value="">Todos los municipios</option></select></label>
                    <label>Confianza<select name="confianza" id="confianza"><option value="">Todas</option><option value="alta">Alta</option><option value="nominal">Nominal</option><option value="baja">Baja</option><option value="desconocida">Desconocida</option></select></label>
                    <label>Desde <small>Incluido</small><input type="datetime-local" id="desde" name="desde" required step="1"></label>
                    <label>Hasta <small>Excluido</small><input type="datetime-local" id="hasta" name="hasta" required step="1"></label>
                    <div class="acciones-filtros"><button type="submit" class="boton-principal" id="consultar">Consultar focos</button><button type="button" class="boton-suave" id="limpiar-periodo">Limpiar fechas</button><button type="button" class="boton-suave" id="cancelar-filtros" hidden>Deshacer cambios</button></div>
                </div>
            </fieldset>
            <p class="ayuda-filtros">Máximo 7 días. Los calendarios se limitan entre sí; para elegir otro período lejano, pulsa Limpiar fechas. El municipio debe pertenecer a la provincia; sin selección, se consulta todo el departamento.</p>
        </form>
        <p id="estado-catalogo" role="status">Cargando catálogo territorial…</p>
        <button type="button" id="reintentar-catalogo" class="boton-suave" hidden>Reintentar catálogo</button>
    </section>

    <div class="estado-consulta" id="aviso-consulta" role="status" aria-live="polite"><strong id="titulo-estado">Listo para explorar</strong><p id="mensaje-estado">Revisa los filtros y pulsa Consultar focos. Los resultados se obtienen de la fuente externa.</p><div class="acciones-estado"><button type="button" id="reintentar" class="boton-suave" hidden>Reintentar consulta</button><button type="button" id="reiniciar" class="boton-suave" hidden>Reiniciar desde la primera página</button></div></div>
    <noscript><p class="estado-consulta">Esta pantalla interactiva requiere JavaScript. Puedes consultar el <a href="{{ url('/api/v1/territorios') }}">catálogo en formato JSON</a>. No se muestran resultados ambientales sin ejecutar la consulta.</p></noscript>

    <section id="resultados-focos" hidden aria-labelledby="titulo-resultados" aria-busy="false">
        <div class="contexto-consulta"><div><span class="etiqueta" id="etiqueta-vigencia">CONSULTA APLICADA</span><h2 id="titulo-resultados">Santa Cruz</h2><p id="periodo-aplicado"></p></div><div class="obtencion"><strong id="tipo-obtencion"></strong><span id="fecha-obtencion"></span></div></div>
        <div class="indicadores" aria-label="Resumen del conjunto completo">
            <article><span>Detecciones del conjunto</span><strong id="total-focos">—</strong><small>Todos los resultados filtrados</small></article>
            <article><span>Confianza alta</span><strong id="confianza-alta">—</strong><small>Confianza de detección, no gravedad</small></article>
            <article><span>FRP máxima</span><strong id="frp-maxima">—</strong><small id="frp-validos">Potencia radiativa · MW</small></article>
            <article><span>Registros con faltantes</span><strong id="total-faltantes">—</strong><small>Los valores ausentes no son cero</small></article>
        </div>
        <p class="cobertura" id="estado-cobertura"></p>
        <div class="rejilla-visor">
            <section class="panel-mapa" aria-labelledby="titulo-mapa">
                <div class="encabezado-panel"><h3 id="titulo-mapa">El territorio, de cerca</h3><button type="button" id="centrar-territorio" class="boton-texto">Centrar territorio</button></div>
                <p id="alcance-mapa" class="nota-pagina"></p>
                <div id="mapa-focos" tabindex="-1" aria-label="Mapa interactivo de las detecciones de la página actual"></div>
                <p class="nota-pagina">Los números agrupan detecciones próximas de esta página. Pulsa un grupo para acercarte y elegir un registro; las coordenadas no se desplazan.</p>
                <section id="grupo-focos" class="grupo-focos" aria-labelledby="titulo-grupo" hidden>
                    <div class="encabezado-panel"><h4 id="titulo-grupo" tabindex="-1"></h4><button type="button" id="cerrar-grupo" class="boton-suave">Cerrar grupo</button></div>
                    <div id="lista-grupo"></div>
                </section>
                <p id="error-mapa" class="aviso-mapa" role="status" hidden></p>
                <div class="leyenda"><span><i class="alta"></i>Alta</span><span><i class="nominal"></i>Nominal</span><span><i class="baja"></i>Baja</span><span><i class="desconocida"></i>Desconocida</span><small>Color de punto = confianza</small></div>
            </section>
            <section class="panel-listado" aria-labelledby="titulo-listado">
                <div class="encabezado-panel"><h3 id="titulo-listado">Detecciones</h3><span id="conteo-pagina"></span></div>
                <div id="lista-focos" class="lista-focos"></div>
                <div class="paginador"><button type="button" id="anterior" class="boton-suave">← Anterior</button><span id="pagina-actual"></span><button type="button" id="siguiente" class="boton-suave">Siguiente →</button></div>
            </section>
        </div>
        <section id="detalle-foco" class="panel-detalle" aria-labelledby="titulo-detalle" hidden>
            <div class="encabezado-panel"><h3 id="titulo-detalle" tabindex="-1">Detalle de la detección</h3><button type="button" id="cerrar-detalle" class="boton-suave">Cerrar detalle</button></div>
            <dl id="campos-detalle" class="campos-detalle"></dl>
            <p id="faltantes-detalle" class="ayuda-filtros"></p>
        </section>
        <details class="procedencia"><summary>Fuente, cobertura y límites de interpretación</summary><div id="procedencia-consulta"></div></details>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script type="module" src="{{ asset('js/focos-calor/inicio.js') }}"></script>
@endpush
