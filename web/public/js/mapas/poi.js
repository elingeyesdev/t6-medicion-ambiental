export function iniciarPoi(panel, mapa, L) {
    const formulario = panel.querySelector('[data-form-poi]'), estado = panel.querySelector('[data-estado-poi]');
    const lista = panel.querySelector('[data-resultados-poi]'), buscar = formulario.querySelector('[type="submit"]');
    let peticion, turno = 0, marca;
    function limpiar() {
        turno++; peticion?.abort(); lista.replaceChildren(); estado.textContent = '';
        if (marca) mapa.removeLayer(marca);
        marca = null; buscar.disabled = formulario.elements.texto.disabled;
    }
    formulario.addEventListener('reset', limpiar);
    formulario.addEventListener('input', limpiar);
    formulario.elements.pais.addEventListener('change', limpiar);
    formulario.addEventListener('keydown', e => { if (e.key === 'Escape') formulario.reset(); });
    formulario.addEventListener('submit', async e => {
        e.preventDefault(); limpiar();
        if (formulario.elements.texto.disabled || !formulario.reportValidity()) return;
        const actual = turno; peticion = new AbortController(); buscar.disabled = true; estado.textContent = 'Buscando…';
        try {
            const url = new URL(panel.dataset.poi, location.origin);
            url.search = new URLSearchParams({texto: formulario.elements.texto.value.trim(), pais: formulario.elements.pais.value});
            const r = await fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: peticion.signal});
            const datos = await r.json();
            if (turno !== actual) return;
            if (!r.ok) throw new Error(datos.error?.mensaje ?? 'No se pudo buscar. Revisa el nombre y el país.');
            if (!Array.isArray(datos.datos)) throw new Error('No se pudo leer la búsqueda.');
            estado.textContent = datos.datos.length ? 'Elige un establecimiento.' : 'No se encontraron establecimientos.';
            for (const lugar of datos.datos) {
                const fila = document.createElement('li'), boton = document.createElement('button');
                boton.type = 'button'; boton.className = 'lugar-resultado';
                const nombre = document.createElement('strong'), descripcion = document.createElement('span');
                nombre.textContent = lugar.nombre; descripcion.textContent = lugar.descripcion; boton.append(nombre, descripcion);
                boton.addEventListener('click', () => {
                    if (marca) mapa.removeLayer(marca);
                    marca = L.marker([lugar.latitud, lugar.longitud], {title: lugar.nombre}).addTo(mapa).bindTooltip(nombre.cloneNode(true));
                    mapa.setView([lugar.latitud, lugar.longitud], 14, {animate: false});
                    estado.textContent = lugar.nombre + ' · ' + lugar.descripcion;
                });
                fila.append(boton); lista.append(fila);
            }
        } catch (e) {
            if (turno !== actual || e.name === 'AbortError') return;
            estado.textContent = e instanceof TypeError || e instanceof SyntaxError ? 'No se pudo conectar. Inténtalo de nuevo.' : e.message;
        } finally { if (turno === actual) buscar.disabled = false; }
    });
    return configuracion => {
        limpiar();
        formulario.elements.pais.replaceChildren(...Object.entries(configuracion.paises).map(([id, nombre]) => new Option(nombre, id)));
        for (const n of [formulario.elements.pais, formulario.elements.texto, buscar]) n.disabled = !configuracion.habilitado;
        estado.textContent = configuracion.habilitado ? 'Elige un país y escribe el establecimiento.' : 'La búsqueda de establecimientos no está disponible.';
    };
}
