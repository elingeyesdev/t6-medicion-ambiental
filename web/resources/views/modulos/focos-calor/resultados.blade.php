<section id="resultados-focos" hidden aria-labelledby="titulo-resultados" aria-busy="false">
        <div class="contexto-consulta"><div><span class="etiqueta" id="etiqueta-vigencia">CONSULTA APLICADA</span><h2 id="titulo-resultados">Santa Cruz</h2><p id="periodo-aplicado"></p></div><div class="obtencion"><strong id="tipo-obtencion"></strong><span id="fecha-obtencion"></span></div></div>
        <div class="indicadores" aria-label="Resumen del conjunto completo">
            <article><span>Detecciones</span><strong id="total-focos">—</strong><small>Conjunto filtrado</small></article>
            <article><span>Confianza alta</span><strong id="confianza-alta">—</strong><small>Calidad de detección</small></article>
            <article><span>FRP máxima</span><strong id="frp-maxima">—</strong><small>Potencia radiativa</small></article>
            <article><span>Con faltantes</span><strong id="total-faltantes">—</strong><small>Registros</small></article>
        </div>
            <section class="panel-listado" aria-labelledby="titulo-listado">
                <div class="encabezado-panel"><h3 id="titulo-listado">Detecciones</h3><span id="conteo-pagina"></span></div>
                <div id="lista-focos" class="lista-focos"></div>
                <div class="paginador"><button type="button" id="anterior" class="boton-suave">← Anterior</button><span id="pagina-actual"></span><button type="button" id="siguiente" class="boton-suave">Siguiente →</button></div>
            </section>
        <section id="detalle-foco" class="panel-detalle" aria-labelledby="titulo-detalle" hidden>
            <div class="encabezado-panel"><h3 id="titulo-detalle" tabindex="-1">Detalle de la detección</h3><button type="button" id="cerrar-detalle" class="boton-suave">Cerrar detalle</button></div>
            <dl id="campos-detalle" class="campos-detalle"></dl>
            <p id="faltantes-detalle" class="ayuda-filtros"></p>
        </section>
        <details class="procedencia"><summary>Fuente y detalles</summary><p class="cobertura" id="estado-cobertura"></p><p id="frp-validos"></p><p>La confianza no expresa gravedad. FRP no mide superficie quemada. Los faltantes no equivalen a cero.</p><div id="procedencia-consulta"></div></details>
    </section>
