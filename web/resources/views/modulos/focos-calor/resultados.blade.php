<section id="resultados-focos" hidden aria-labelledby="titulo-resultados" aria-busy="false">
    <div class="contexto-consulta resultados-cabecera">
        <span id="etiqueta-vigencia">Resultados</span>
        <h2 id="titulo-resultados">Santa Cruz</h2>
        <p id="periodo-aplicado"></p>
    </div>
    <div class="indicadores" aria-label="Resumen del conjunto completo">
        <article class="indicador-calor"><span><x-panel.icono nombre="flame" /> Detecciones de calor</span><strong id="total-focos">—</strong></article>
        <article class="indicador-confianza"><span>Confianza alta</span><strong id="confianza-alta">—</strong></article>
    </div>
    <section class="panel-listado" aria-labelledby="titulo-listado">
        <div class="encabezado-panel"><h3 id="titulo-listado">Ubicaciones</h3><span id="conteo-pagina"></span></div>
        <div id="lista-focos" class="lista-focos"></div>
        <div class="paginador"><button type="button" id="anterior" class="boton-suave">← Anterior</button><span id="pagina-actual"></span><button type="button" id="siguiente" class="boton-suave">Siguiente →</button></div>
    </section>
    <section id="detalle-foco" class="panel-detalle" aria-labelledby="titulo-detalle" hidden>
        <div class="encabezado-panel"><h3 id="titulo-detalle" tabindex="-1">Detección seleccionada</h3><button type="button" id="cerrar-detalle" aria-label="Cerrar detalle" class="boton-suave">Cerrar</button></div>
        <dl id="campos-detalle" class="campos-detalle"></dl>
        <details class="detalle-extra"><summary>Más información</summary>
            <dl id="campos-detalle-extra" class="campos-detalle"></dl>
            <p id="faltantes-detalle" class="ayuda-filtros"></p>
        </details>
    </section>
    <details class="procedencia"><summary>Fuente y detalles</summary>
        <div class="metadatos-resumen"><p>Potencia radiativa máxima: <strong id="frp-maxima">—</strong></p><p>Registros incompletos: <strong id="total-faltantes">—</strong></p></div>
        <div class="obtencion"><strong id="tipo-obtencion"></strong><span id="fecha-obtencion"></span></div>
        <p class="cobertura" id="estado-cobertura"></p><p id="frp-validos"></p>
        <p>La confianza no expresa gravedad. FRP es potencia radiativa, no superficie quemada. Los faltantes no equivalen a cero.</p>
        <div id="procedencia-consulta"></div>
    </details>
</section>
