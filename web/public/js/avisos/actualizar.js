// Consultas cortas: no mantienen ocupado el único proceso de artisan serve.
export function iniciarAvisos(doc = document, consultar = fetch, programar = setTimeout) {
    const lista = doc.getElementById('avisos-lista');
    const url = lista?.dataset.novedades;
    if (!url) return;
    const contador = doc.getElementById('avisos-contador');
    const paginacion = doc.getElementById('avisos-paginacion');
    const estado = doc.getElementById('avisos-actualizacion');
    let revision = null;
    let espera = 10000;
    let detenido = false;
    async function actualizar() {
        if (detenido) return;
        if (doc.hidden) { programar(actualizar, 10000); return; }
        const control = new AbortController();
        const timeout = setTimeout(() => control.abort(), 5000);
        try {
            const respuesta = await consultar(url, { credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: control.signal });
            if ([401, 403, 419].includes(respuesta.status) || respuesta.redirected) {
                detenido = true;
                estado.textContent = 'Vuelve a ingresar para consultar tus avisos.';
                return;
            }
            if (!respuesta.ok) throw new Error('consulta');
            const datos = await respuesta.json();
            if (!Number.isInteger(datos.sinLeer) || typeof datos.revision !== 'string' || typeof datos.html !== 'string' || typeof datos.paginacion !== 'string') throw new Error('respuesta');
            contador.textContent = `${datos.sinLeer} sin leer`;
            // Conserva el foco si alguien está usando un enlace o un botón de la lista.
            if (revision !== datos.revision && !lista.contains(doc.activeElement) && !paginacion?.contains(doc.activeElement)) {
                lista.innerHTML = datos.html;
                if (paginacion) paginacion.innerHTML = datos.paginacion;
                revision = datos.revision;
            }
            estado.textContent = '';
            espera = 10000;
        } catch {
            estado.textContent = 'No pudimos actualizar tus avisos. Lo intentaremos de nuevo.';
            espera = Math.min(60000, espera * 2);
        } finally {
            clearTimeout(timeout);
            if (!detenido) programar(actualizar, espera);
        }
    }
    programar(actualizar, 10000);
    return () => { detenido = true; };
}

if (typeof document !== 'undefined') iniciarAvisos();
