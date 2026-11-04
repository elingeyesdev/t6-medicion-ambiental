<section id="capas-mapa" class="capas-panel" aria-label="Capas del ambiente"
    data-catalogo="{{ route('mapa.capas') }}" data-poi="{{ route('mapa.poi') }}">
    <details open>
        <summary>Capas del ambiente</summary>
        <div class="capas-controles">
            <p>Imágenes del ambiente, separadas de las mediciones de puntos.</p>
            <button type="button" class="btn btn-outline-primary" data-actualizar>Actualizar capas</button>
            <button type="button" class="btn btn-primary" data-fechas-nuevas hidden>Hay nuevas fechas · Actualizar fechas</button>
            <p data-estado role="status" aria-live="polite">Consultando imágenes disponibles…</p>
            <label class="form-check"><input type="checkbox" class="form-check-input" data-visible disabled><span class="form-check-label">Mostrar capa</span></label>
            <fieldset data-controles disabled>
                <legend class="visually-hidden">Selección y recorrido de capas</legend>
                <label for="capa-a" class="form-label">Capa A · izquierda al comparar</label>
                <select id="capa-a" class="form-select" data-capa="a"></select>
                <label for="capa-fecha-a" class="form-label">Fecha de A · hora de Bolivia</label>
                <select id="capa-fecha-a" class="form-select" data-fecha="a"></select>
                <label for="capa-recorrido" class="form-label">Recorrer imágenes disponibles</label>
                <input id="capa-recorrido" class="form-range" type="range" min="0" max="0" step="1" value="0" data-recorrido>
                <div class="capas-acciones">
                    <button type="button" class="btn btn-outline-primary" data-anterior aria-label="Imagen anterior">Anterior</button>
                    <button type="button" class="btn btn-primary" data-reproducir aria-pressed="false">Reproducir</button>
                    <button type="button" class="btn btn-outline-primary" data-siguiente aria-label="Imagen siguiente">Siguiente</button>
                </div>
                <label class="form-check"><input type="checkbox" class="form-check-input" data-comparar><span class="form-check-label">Comparar capas o fechas</span></label>
                <div data-seleccion-b hidden>
                    <label for="capa-b" class="form-label">Capa B · derecha</label>
                    <select id="capa-b" class="form-select" data-capa="b"></select>
                    <label for="capa-fecha-b" class="form-label">Fecha de B · hora de Bolivia</label>
                    <select id="capa-fecha-b" class="form-select" data-fecha="b"></select>
                    <label for="capa-division" class="form-label">División A/B</label>
                    <input id="capa-division" class="form-range" type="range" min="0" max="100" value="50" data-division>
                    <output for="capa-division" data-porcentaje>50 % de A · 50 % de B</output>
                </div>
                <label for="capa-opacidad" class="form-label">Opacidad</label>
                <input id="capa-opacidad" class="form-range" type="range" min="10" max="100" value="65" data-opacidad>
                <div data-control-viento hidden>
                    <label class="form-check"><input type="checkbox" class="form-check-input" data-animar-viento aria-describedby="viento-ayuda"><span class="form-check-label">Animar viento</span></label>
                    <p id="viento-ayuda">Muestra la dirección. No representa el recorrido del humo.</p>
                </div>
                <div data-leyendas aria-label="Leyendas de capas"></div>
                <button type="button" class="btn btn-outline-primary" data-leer>Leer capas en el centro</button>
                <div data-lectura role="status" aria-live="polite"></div>
            </fieldset>
        </div>
    </details>
    <details class="capas-poi" @if(!config('capas.poi_habilitado')) hidden @endif>
        <summary>Buscar establecimientos</summary>
        <div class="capas-controles">
            <p>Disponible en algunos países de Estados Unidos, Canadá y Europa. Sin cobertura de Bolivia.</p>
            <form data-form-poi role="search">
                <label class="form-label" for="poi-pais">País disponible</label>
                <select id="poi-pais" class="form-select" name="pais" required disabled></select>
                <label class="form-label" for="poi-texto">Establecimiento</label>
                <input id="poi-texto" class="form-control" type="search" name="texto" placeholder="Museo, hospital o comercio" minlength="2" maxlength="120" autocomplete="off" required disabled>
                <div class="capas-acciones"><button type="submit" class="btn btn-primary" disabled>Buscar</button><button type="reset" class="btn btn-outline-primary">Limpiar</button></div>
            </form>
            <p data-estado-poi role="status" aria-live="polite">Consulta los países disponibles.</p>
            <ul data-resultados-poi aria-label="Establecimientos encontrados"></ul>
            <a href="https://www.mapbox.com/about/maps/" target="_blank" rel="noopener noreferrer">Mapbox</a>
        </div>
    </details>
</section>
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/mapas/capas.css') }}">
@endpush
