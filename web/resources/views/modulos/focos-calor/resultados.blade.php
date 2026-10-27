<section id="resultados-focos" hidden aria-labelledby="titulo-resultados" aria-busy="false">
        <div class="contexto-consulta"><div><span class="etiqueta" id="etiqueta-vigencia">CONSULTA APLICADA</span><h2 id="titulo-resultados">Santa Cruz</h2><p id="periodo-aplicado"></p></div><div class="obtencion"><strong id="tipo-obtencion"></strong><span id="fecha-obtencion"></span></div></div>
        <div class="indicadores" aria-label="Resumen del conjunto completo">
            <article><span>Detecciones del conjunto</span><strong id="total-focos">—</strong><small>Todos los resultados filtrados</small></article>
            <article><span>Confianza alta</span><strong id="confianza-alta">—</strong><small>Confianza de detección, no gravedad</small></article>
            <article><span>FRP máxima</span><strong id="frp-maxima">—</strong><small id="frp-validos">Potencia radiativa · MW</small></article>
            <article><span>Registros con faltantes</span><strong id="total-faltantes">—</strong><small>Los valores ausentes no son cero</small></article>
        </div>
        <p class="cobertura" id="estado-cobertura"></p>
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
        <details class="procedencia"><summary>Fuente, cobertura y límites de interpretación</summary><div id="procedencia-consulta"></div></details>
    </section>
