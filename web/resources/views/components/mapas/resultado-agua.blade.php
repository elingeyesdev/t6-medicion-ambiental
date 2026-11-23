    <div id="agua-resultado" hidden>
        <h3 id="agua-lugar"></h3><p id="agua-fechas" @if(request()->routeIs('mapa.agua')) hidden @endif></p><p id="agua-cobertura"></p>
        <dl id="agua-resumen"></dl>
        <ul class="agua-leyenda" aria-label="Leyenda de agua"><li data-clase="agua_persistente">Agua en ambas fechas</li><li data-clase="agua_nueva">Agua nueva</li><li data-clase="agua_retirada">Agua retirada</li><li data-clase="no_comparable">Sin comparación</li></ul>
        <label id="agua-contornos" class="agua-contornos" hidden>
            <input id="agua-contorno-original" class="form-check-input" type="checkbox">
            Mostrar contorno original
        </label>
        <section id="agua-listado" aria-labelledby="agua-listado-titulo" hidden>
            <h3 id="agua-listado-titulo">Ubicaciones</h3>
            <div id="agua-ubicaciones"></div>
        </section>
        <details><summary>Sobre esta comparación</summary><p id="agua-fuente"></p><p>La vista suavizada redondea bordes de la cuadrícula. Puedes consultar la forma original del recorte.</p><ul id="agua-advertencias"></ul><p>Un cambio de agua no confirma una inundación.</p></details>
    </div>
    <details><summary>Fondo del mapa</summary><label class="visually-hidden" for="mon-base">Fondo del mapa</label><select id="mon-base" class="form-select" disabled><option value="claro">Claro</option><option value="relieve">Relieve</option><option value="satelite">Satélite</option><option value="calles">Calles</option></select></details>
