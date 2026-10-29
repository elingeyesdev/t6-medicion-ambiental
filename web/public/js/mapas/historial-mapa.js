import { instanteBolivia } from './consulta-monitoreo.js';

export function compararLecturas(referencia, actual) {
    if (referencia.metrica !== actual.metrica || referencia.unidad !== actual.unidad) return null;
    const clave = l => JSON.stringify([l.origen, l.fuente]);
    const primeras = new Map(referencia.lecturas.map(l => [clave(l), l]));
    const segundas = new Map(actual.lecturas.map(l => [clave(l), l]));
    return [...new Set([...primeras.keys(), ...segundas.keys()])].map(k => {
        const a = primeras.get(k) ?? null, b = segundas.get(k) ?? null;
        return {origen: (a ?? b).origen, fuente: (a ?? b).fuente, a, b,
            cambio: a && b ? Math.round((b.valor - a.valor) * 10000) / 10000 : null};
    });
}

export function iniciarRecorrido(formulario, consultar) {
    const activar = formulario.elements.por_momento;
    const momento = formulario.elements.momento;
    const panel = document.getElementById('recorrido-mapa');
    const barra = document.getElementById('recorrido-barra');
    const anterior = document.getElementById('recorrido-anterior');
    const siguiente = document.getElementById('recorrido-siguiente');
    const local = ms => new Date(ms - 4 * 3600000).toISOString().slice(0, 16);
    const fecha = new Intl.DateTimeFormat('es-BO', {timeZone: 'America/La_Paz', dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23'});

    function actualizar(reiniciar = false) {
        panel.hidden = !activar.checked;
        momento.disabled = !activar.checked;
        if (!activar.checked) return;
        try {
            const desde = instanteBolivia(formulario.elements.desde.value);
            const ultimo = instanteBolivia(formulario.elements.hasta.value) - 60000;
            if (ultimo < desde) return;
            momento.min = local(desde); momento.max = local(ultimo);
            if (reiniciar || !momento.value) momento.value = local(ultimo);
            const elegido = instanteBolivia(momento.value);
            barra.min = '0'; barra.max = String((ultimo - desde) / 60000);
            barra.value = String((elegido - desde) / 60000);
            barra.setAttribute('aria-valuetext', fecha.format(new Date(elegido)) + ' · Bolivia');
            anterior.disabled = elegido <= desde;
            siguiente.disabled = elegido >= ultimo;
        } catch { /* La consulta muestra el error de fechas sin reemplazarlas. */ }
    }
    activar.addEventListener('change', () => { actualizar(); consultar(); });
    for (const campo of ['desde', 'hasta']) formulario.elements[campo].addEventListener('change', () => actualizar(true));
    momento.addEventListener('change', () => { actualizar(); consultar(); });
    barra.addEventListener('input', () => { momento.value = local(instanteBolivia(formulario.elements.desde.value) + +barra.value * 60000); actualizar(); });
    barra.addEventListener('change', () => consultar());
    for (const [boton, direccion] of [[anterior, -1], [siguiente, 1]]) boton.addEventListener('click', () => {
        const minuto = Math.max(+barra.min, Math.min(+barra.max, +barra.value + direccion * 60));
        momento.value = local(instanteBolivia(formulario.elements.desde.value) + minuto * 60000);
        actualizar(); consultar();
    });
    actualizar();
}
