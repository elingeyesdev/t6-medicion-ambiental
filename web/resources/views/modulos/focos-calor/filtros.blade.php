<section class="panel-filtros" aria-labelledby="titulo-filtros">
        <div class="encabezado-panel"><h2 id="titulo-filtros">Navegación y período</h2><span>Hora de Bolivia · UTC−4</span></div>
        <form id="filtros-focos">
            <fieldset id="campos-filtros" disabled>
                <legend class="solo-lector">Territorio, período y confianza</legend>
                <div class="rejilla-filtros">
                    <label>Provincia<select class="form-select" name="provincia" id="provincia"><option value="">Todo Santa Cruz</option></select></label>
                    <label>Municipio<select class="form-select" name="municipio" id="municipio"><option value="">Todos los municipios</option></select></label>
                    <label>Confianza<select class="form-select" name="confianza" id="confianza"><option value="">Todas</option><option value="alta">Alta</option><option value="nominal">Nominal</option><option value="baja">Baja</option><option value="desconocida">Desconocida</option></select></label>
                    <label>Desde <small>Incluido</small><input class="form-control" type="datetime-local" id="desde" name="desde" required step="1"></label>
                    <label>Hasta <small>Excluido</small><input class="form-control" type="datetime-local" id="hasta" name="hasta" required step="1"></label>
                    <div class="acciones-filtros"><button type="submit" class="btn boton-principal" id="consultar">Consultar focos</button><button type="button" class="btn boton-suave" id="limpiar-periodo">Limpiar fechas</button><button type="button" class="btn boton-suave" id="cancelar-filtros" hidden>Deshacer cambios</button></div>
                </div>
            </fieldset>
            <details class="ayuda-consulta"><summary>Cómo consultar</summary><p class="ayuda-filtros">Máximo 7 días. Los calendarios se limitan entre sí; para elegir otro período lejano, pulsa Limpiar fechas. El municipio debe pertenecer a la provincia; sin selección, se consulta todo el departamento.</p></details>
        </form>
        <p id="estado-catalogo" role="status">Cargando catálogo territorial…</p>
        <button type="button" id="reintentar-catalogo" class="btn boton-suave" hidden>Reintentar catálogo</button>
    </section>
