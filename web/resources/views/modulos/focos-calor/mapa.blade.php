<section class="panel-mapa" aria-labelledby="titulo-mapa">
    <div class="encabezado-panel"><h3 id="titulo-mapa">Mapa de detecciones</h3><button type="button" id="centrar-territorio" class="boton-texto">Centrar territorio</button></div>
    <p id="alcance-mapa" class="nota-pagina"></p>
    <div id="mapa-focos" tabindex="-1" aria-label="Mapa interactivo de las detecciones de la página actual"></div>
    <p class="nota-pagina">Pulsa un grupo para ver sus detecciones.</p>
    <section id="grupo-focos" class="grupo-focos" aria-labelledby="titulo-grupo" hidden>
        <div class="encabezado-panel"><h4 id="titulo-grupo" tabindex="-1"></h4><button type="button" id="cerrar-grupo" class="boton-suave">Cerrar grupo</button></div>
        <div id="lista-grupo"></div>
    </section>
    <p id="error-mapa" class="aviso-mapa" role="status" hidden></p>
    <div class="leyenda"><span><i class="alta"></i>Alta</span><span><i class="nominal"></i>Nominal</span><span><i class="baja"></i>Baja</span><span><i class="desconocida"></i>Desconocida</span><small>Color de punto = confianza</small></div>
</section>
