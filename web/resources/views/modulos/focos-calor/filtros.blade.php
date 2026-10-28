<section class="panel-filtros" aria-labelledby="titulo-filtros">
        <h2 class="solo-lector" id="titulo-filtros">Filtros de la consulta</h2>
        <form id="filtros-focos">
            <fieldset id="campos-filtros" disabled>
                <legend class="solo-lector">Territorio, período y confianza</legend>
                <div class="rejilla-filtros">
                    <label>Provincia<select class="form-select" name="provincia" id="provincia"><option value="">Todo Santa Cruz</option></select></label>
                    <label>Municipio<select class="form-select" name="municipio" id="municipio"><option value="">Todos los municipios</option></select></label>
                    <label>Confianza<select class="form-select" name="confianza" id="confianza"><option value="">Todas</option><option value="alta">Alta</option><option value="nominal">Nominal</option><option value="baja">Baja</option><option value="desconocida">Desconocida</option></select></label>
                    <div class="periodo-rapido" role="group" aria-label="Elegir un período reciente"><button type="button" class="btn boton-suave" data-periodo-dias="1">Últimas 24 horas</button><button type="button" class="btn boton-suave" data-periodo-dias="7">Últimos 7 días</button></div>
                    <label>Desde<input class="form-control" type="datetime-local" id="desde" name="desde" required step="1" aria-describedby="ayuda-fechas"></label>
                    <label>Hasta<input class="form-control" type="datetime-local" id="hasta" name="hasta" required step="1" aria-describedby="ayuda-fechas"></label>
                    <p class="hora-consulta">Hora de Bolivia · Máximo 7 días.</p>
                    <div class="acciones-filtros"><button type="submit" class="btn boton-principal" id="consultar">Consultar focos</button><button type="button" class="btn boton-suave" id="limpiar-periodo">Limpiar fechas</button><button type="button" class="btn boton-suave" id="cancelar-filtros" hidden>Deshacer cambios</button></div>
                </div>
            </fieldset>
        </form>
        <details class="ayuda-consulta"><summary>Acerca de esta consulta</summary>
            <p id="ayuda-fechas">Puedes consultar hasta 7 días, sin fechas futuras. Se incluye Desde y se termina justo antes de Hasta. Las horas corresponden a Bolivia.</p>
            <p>Sin provincia ni municipio se consulta todo Santa Cruz.</p>
            <p>Fuente: NASA FIRMS · VIIRS. Una detección térmica <strong>no confirma un incendio</strong>.</p>
            <p>Límites territoriales de referencia: OCHA/HDX.</p>
        </details>
        <p id="estado-catalogo" role="status">Cargando…</p>
        <button type="button" id="reintentar-catalogo" class="btn boton-suave" hidden>Reintentar catálogo</button>
    </section>
