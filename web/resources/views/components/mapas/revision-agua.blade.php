<p class="agua-prueba-fija">Datos de prueba</p>
<section id="revision-agua" class="revision-agua" aria-label="Revisión de agua">
    <header><h2>Agua y cambios</h2><button type="button" class="btn btn-outline-primary" id="agua-cerrar" aria-label="Cerrar agua y cambios">×</button></header>
    <p class="alert alert-warning"><strong>Datos de prueba</strong> · Estas figuras no representan una inundación real.</p>
    <label for="agua-caso" class="form-label">Comparación de prueba</label>
    <select id="agua-caso" class="form-select"><option value="cambio">Cambios de agua</option><option value="sin-cambios">Sin cambios</option><option value="sin-datos">Sin datos comparables</option><option value="error">Error de consulta</option></select>
    <button type="button" class="btn btn-primary" id="agua-consultar">Ver comparación</button>
    <p id="agua-estado" role="status" aria-live="polite"></p>
    <div id="agua-resultado" hidden>
        <h3 id="agua-lugar"></h3><p id="agua-fechas"></p><p id="agua-cobertura"></p>
        <dl id="agua-resumen"></dl>
        <ul class="agua-leyenda" aria-label="Leyenda de agua"><li data-clase="agua_persistente">Agua en ambas fechas</li><li data-clase="agua_nueva">Agua nueva</li><li data-clase="agua_retirada">Agua retirada</li><li data-clase="no_comparable">Sin comparación</li></ul>
        <h3>Ubicaciones</h3><ul id="agua-ubicaciones"></ul>
        <details><summary>Sobre esta comparación</summary><p id="agua-fuente"></p><p>Un cambio de agua no confirma una inundación.</p></details>
    </div>
</section>
<button type="button" id="agua-reabrir" class="btn btn-primary agua-reabrir" hidden>Agua y cambios</button>
<script type="application/json" id="agua-ejemplos">{!! json_encode(collect(['cambio', 'sin-cambios', 'sin-datos'])->mapWithKeys(fn ($caso) => [$caso => json_decode(file_get_contents(base_path('../compartido/api/inundaciones/ejemplos/'.$caso.'.json')), true)]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
