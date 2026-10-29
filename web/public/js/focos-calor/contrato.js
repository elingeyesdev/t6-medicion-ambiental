/** Fechas de la interfaz en Bolivia; solicitudes y metadatos canónicos en UTC. */
export function aUtc(valor) {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/.test(valor)) throw new Error('Revisa las fechas y horas de la consulta.');
    const completo = valor.length === 16 ? `${valor}:00` : valor;
    const fecha = new Date(`${completo}-04:00`);
    if (!Number.isFinite(fecha.getTime()) || aLocal(fecha.toISOString()) !== completo) throw new Error('La fecha o la hora no es válida.');
    return fecha.toISOString().replace('.000Z', 'Z');
}

export function aLocal(utc) {
    const fecha = new Date(utc);
    if (!Number.isFinite(fecha.getTime())) return '';
    return new Date(fecha.getTime() - 4 * 3600000).toISOString().slice(0, 19);
}

export function validarInstanteUrl(texto) {
    const partes = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/.exec(texto ?? '');
    if (!partes || /[+-]14:(?!00)/.test(texto)) throw new Error('Las fechas de la URL requieren instantes ISO 8601 válidos con zona explícita.');
    const [anio, mes, dia, hora, minuto, segundo] = partes.slice(1, 7).map(Number);
    const f = new Date(Date.UTC(anio, mes - 1, dia, hora, minuto, segundo));
    if (f.getUTCFullYear() !== anio || f.getUTCMonth() !== mes - 1 || f.getUTCDate() !== dia || f.getUTCHours() !== hora || f.getUTCMinutes() !== minuto || f.getUTCSeconds() !== segundo) throw new Error('La fecha o la hora de la URL no es válida.');
    return texto;
}

export function validarPeriodoLocal(inicio, fin, ahora = Date.now()) {
    const desde = aUtc(inicio);
    const hasta = aUtc(fin);
    const diferencia = new Date(hasta) - new Date(desde);
    if (diferencia <= 0 || diferencia > 7 * 86400000) throw new Error('Desde debe ser anterior a Hasta. El máximo es de 7 días (168 horas).');
    if (new Date(hasta).getTime() > ahora) throw new Error('Elige fechas hasta el momento actual. No se pueden consultar días futuros.');
    return { desde, hasta };
}

export function filtrosDelFormulario(formulario) {
    const datos = new FormData(formulario);
    const { desde, hasta } = validarPeriodoLocal(datos.get('desde'), datos.get('hasta'));
    const filtros = { desde, hasta, modo: 'externo' };
    for (const nombre of ['provincia', 'municipio', 'confianza']) {
        if (datos.get(nombre)) filtros[nombre] = datos.get(nombre);
    }
    return filtros;
}

export function fechaBolivia(utc) {
    if (!utc || !Number.isFinite(new Date(utc).getTime())) return 'No informada';
    return new Intl.DateTimeFormat('es-BO', { timeZone: 'America/La_Paz', dateStyle: 'short', timeStyle: 'short', hour12: false }).format(new Date(utc));
}

export function numero(valor, unidad = '') {
    return valor === null || valor === undefined ? 'Sin dato' : `${new Intl.NumberFormat('es-BO', { maximumFractionDigits: 2 }).format(valor)}${unidad ? ` ${unidad}` : ''}`;
}

export class ErrorApi extends Error {
    constructor(mensaje, codigo = 'CONEXION', estado = 0) { super(mensaje); this.codigo = codigo; this.estado = estado; }
}

export async function solicitarJson(url, signal, transporte = fetch) {
    let respuesta;
    try { respuesta = await transporte(url, { signal, headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' }); }
    catch (error) { if (signal?.aborted) throw error; throw new ErrorApi('No se pudo conectar con GeoSur. Comprueba tu conexión y reintenta.'); }
    let datos;
    try { datos = await respuesta.json(); }
    catch { throw new ErrorApi('El servidor no devolvió una respuesta legible. Reintenta más tarde.', 'RESPUESTA_INVALIDA', respuesta.status); }
    if (!respuesta.ok) throw new ErrorApi(datos.error?.mensaje || 'No se pudo completar la consulta.', datos.error?.codigo || 'ERROR_HTTP', respuesta.status);
    return datos;
}

export function comprobarRespuesta(r, pagina) {
    if (r?.versionContrato !== '1.0-m2-candidata' || !Array.isArray(r.datos) || !Array.isArray(r.resumen?.indicadores)
        || !Array.isArray(r.advertencias) || !Array.isArray(r.fuentes) || !r.consulta?.periodo || !r.consulta?.territorio
        || !r.consulta?.obtencion || !r.cobertura || r.paginacion?.pagina !== pagina || r.representacionMapa?.alcance !== 'pagina'
        || !Number.isInteger(r.paginacion.total) || r.paginacion.total < 0 || r.resumen.totalRegistros !== r.paginacion.total
        || typeof r.paginacion.versionConjunto !== 'string' || r.datos.some(f => !f || typeof f.identificador !== 'string' || !f.atributos || !f.fechas || !f.territorio || !Array.isArray(f.faltantes))) {
        throw new ErrorApi('La respuesta no coincide con el contrato de consulta. Reintenta o informa al equipo.', 'CONTRATO_INCOMPATIBLE');
    }
    return r;
}
