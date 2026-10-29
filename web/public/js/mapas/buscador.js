export function iniciarBuscadorLugar(url, seleccionar) {
    const formulario = document.getElementById('buscar-lugar');
    const entrada = formulario.elements.texto;
    const resultados = document.getElementById('lugares-encontrados');
    const estado = document.getElementById('estado-buscador');
    const buscar = formulario.querySelector('[type="submit"]');
    let peticion = null;
    let turno = 0;

    function limpiar() {
        turno++;
        peticion?.abort();
        resultados.replaceChildren();
        estado.textContent = '';
        buscar.disabled = false;
    }

    formulario.addEventListener('reset', limpiar);
    entrada.addEventListener('input', limpiar);
    entrada.addEventListener('keydown', evento => {
        if (evento.key === 'Escape') formulario.reset();
    });

    formulario.addEventListener('submit', async evento => {
        evento.preventDefault();
        limpiar();
        const actual = turno;
        const texto = entrada.value.trim();
        if (texto.length < 2) return;
        peticion = new AbortController();
        estado.textContent = 'Buscando…';
        buscar.disabled = true;
        try {
            const destino = new URL(url, location.origin);
            destino.searchParams.set('texto', texto);
            const r = await fetch(destino, {headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: peticion.signal});
            const datos = await r.json();
            if (actual !== turno) return;
            if (!r.ok) throw new Error(datos.error?.mensaje ?? 'No se pudo buscar. Inténtalo de nuevo.');
            if (!Array.isArray(datos.datos)) throw new Error('No se pudo leer la búsqueda.');
            estado.textContent = datos.datos.length ? 'Elige el lugar.' : 'No se encontraron lugares con ese nombre.';
            for (const lugar of datos.datos) {
                const fila = document.createElement('li');
                const boton = document.createElement('button');
                boton.type = 'button'; boton.className = 'lugar-resultado';
                const nombre = document.createElement('strong'); nombre.textContent = lugar.nombre;
                const descripcion = document.createElement('span'); descripcion.textContent = lugar.descripcion;
                boton.append(nombre, descripcion);
                boton.addEventListener('click', () => {
                    limpiar();
                    entrada.value = lugar.nombre;
                    seleccionar(lugar);
                });
                fila.append(boton); resultados.append(fila);
            }
        } catch (error) {
            if (actual === turno && error.name !== 'AbortError') {
                estado.textContent = error instanceof TypeError || error instanceof SyntaxError ? 'No se pudo conectar. Inténtalo de nuevo.' : error.message;
            }
        } finally {
            if (actual === turno) buscar.disabled = false;
        }
    });
}
