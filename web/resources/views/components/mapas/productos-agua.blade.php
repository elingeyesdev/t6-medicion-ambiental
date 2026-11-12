<section id="agua-publicada" class="panel-agua" data-panel-agua data-modo="publicado" aria-label="Cambios de agua">
    <header><h2>Cambios de agua</h2><button type="button" class="btn btn-outline-primary" id="agua-cerrar" aria-label="Cerrar agua y cambios">×</button></header>
    <p id="agua-fondo-estado" class="alert alert-warning" role="status" hidden></p>
    <button type="button" class="btn btn-outline-primary agua-cambiar" id="agua-filtros" aria-controls="agua-seleccion" aria-expanded="true">Ocultar filtros</button>
    <form id="agua-seleccion">
        <label for="agua-territorio" class="form-label">Lugar</label>
        <select id="agua-territorio" class="form-select" disabled></select>
        <label for="agua-comparacion" class="form-label">Comparación</label>
        <select id="agua-comparacion" class="form-select" disabled></select>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary" id="agua-consultar" disabled>Consultar</button>
            <button type="button" class="btn btn-outline-primary" id="agua-actualizar">Actualizar lista</button>
        </div>
    </form>
    <p id="agua-estado" role="status" aria-live="polite">Cargando…</p>
    @include('components.mapas.resultado-agua')
</section>
<button type="button" id="agua-reabrir" class="btn btn-primary agua-reabrir" hidden>Cambios de agua</button>
