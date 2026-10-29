export function crearPresentador({catalogo = {}, preferencias = {}} = {}) {
    let elegidas = {...preferencias};
    function opcion(metrica, base) {
        for (const [clave, grupo] of Object.entries(catalogo)) {
            if (grupo.base === base && grupo.variables.includes(metrica)) {
                return grupo.opciones[elegidas[clave]] ?? grupo.opciones[grupo.defecto];
            }
        }
        return null;
    }
    function numero(metrica, valor, base, diferencia = false) {
        if (typeof valor !== 'number' || !Number.isFinite(valor)) return null;
        const unidad = opcion(metrica, base);
        return unidad ? valor * unidad.factor / unidad.divisor + (diferencia ? 0 : unidad.desplazamiento) : valor;
    }
    return {
        numero,
        unidad: (metrica, base) => opcion(metrica, base)?.simbolo ?? base,
        texto(metrica, valor, base, diferencia = false) {
            const convertido = numero(metrica, valor, base, diferencia);
            if (convertido === null) return 'No disponible';
            const unidad = opcion(metrica, base);
            const formato = new Intl.NumberFormat('es-BO', {maximumFractionDigits: unidad?.decimales ?? 2});
            return (formato.format(Object.is(convertido, -0) ? 0 : convertido) + ' ' + (unidad?.simbolo ?? base)).trim();
        },
        establecer: preferencias => { elegidas = {...preferencias}; },
    };
}

const configuracion = typeof document !== 'undefined' ? document.getElementById('configuracion-unidades') : null;
export const presentacion = crearPresentador(configuracion ? JSON.parse(configuracion.textContent) : {});

export function medida(metrica, valor, unidad, diferencia = false) {
    const n = document.createElement('span');
    n.dataset.medida = metrica;
    n.dataset.valor = typeof valor === 'number' && Number.isFinite(valor) ? String(valor) : '';
    n.dataset.unidad = unidad;
    if (diferencia) n.dataset.diferencia = 'true';
    n.textContent = presentacion.texto(metrica, valor, unidad, diferencia);
    return n;
}

if (typeof document !== 'undefined') {
    for (const formulario of document.querySelectorAll('[data-preferencias-unidades]')) {
        formulario.addEventListener('submit', async evento => {
            evento.preventDefault();
            const estado = formulario.querySelector('[data-estado-unidades]');
            const boton = formulario.querySelector('button[type="submit"]');
            const campos = new FormData(formulario);
            const unidades = Object.fromEntries(['temperatura', 'viento'].map(clave => [clave, campos.get('unidades[' + clave + ']')]));
            boton.disabled = true; estado.textContent = 'Guardando…';
            try {
                const r = await fetch(formulario.action, {method: 'PUT', credentials: 'same-origin',
                    headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': campos.get('_token')}, body: JSON.stringify({unidades})});
                const datos = await r.json();
                if (!r.ok || !datos.unidades) throw new Error(datos.error?.mensaje ?? 'No se pudieron guardar las unidades.');
                presentacion.establecer(datos.unidades);
                for (const n of document.querySelectorAll('[data-medida]')) {
                    n.textContent = presentacion.texto(n.dataset.medida, n.dataset.valor === '' ? null : Number(n.dataset.valor), n.dataset.unidad, n.dataset.diferencia === 'true');
                }
                for (const n of document.querySelectorAll('[data-simbolo]')) n.textContent = presentacion.unidad(n.dataset.simbolo, n.dataset.unidad);
                for (const otro of document.querySelectorAll('[data-preferencias-unidades]')) {
                    for (const [clave, valor] of Object.entries(datos.unidades)) otro.elements.namedItem('unidades[' + clave + ']').value = valor;
                }
                document.dispatchEvent(new CustomEvent('unidades-cambiadas'));
                estado.textContent = 'Unidades guardadas.';
            } catch (e) {
                estado.textContent = e instanceof TypeError || e instanceof SyntaxError ? 'No se pudo conectar. Inténtalo de nuevo.' : e.message;
            } finally { boton.disabled = false; }
        });
    }
}
