<section class="panel-mapa" aria-labelledby="titulo-mapa">
    <div class="encabezado-panel"><h3 id="titulo-mapa">Mapa de detecciones</h3><label class="selector-base"><span class="solo-lector">Vista del mapa</span><select class="form-select" id="base-mapa-focos" disabled><option value="relieve">Relieve</option><option value="satelite">Satélite</option><option value="calles">Calles</option></select></label><button type="button" id="centrar-territorio" class="boton-texto">Centrar territorio</button></div>
    <p id="alcance-mapa" class="solo-lector"></p>
    <div id="mapa-focos" tabindex="-1" aria-label="Mapa interactivo de las detecciones de la página actual" aria-describedby="alcance-mapa"></div>
    <section id="grupo-focos" class="grupo-focos" aria-labelledby="titulo-grupo" hidden>
        <div class="encabezado-panel"><h4 id="titulo-grupo" tabindex="-1"></h4><button type="button" id="cerrar-grupo" aria-label="Cerrar grupo" class="boton-suave">Cerrar</button></div>
        <div id="lista-grupo"></div>
    </section>
    <p id="error-mapa" class="aviso-mapa" role="status" hidden></p>
    <div class="leyenda" aria-label="Símbolo de las detecciones"><span><x-panel.icono nombre="flame" /> Detecciones de calor</span></div>
</section>
