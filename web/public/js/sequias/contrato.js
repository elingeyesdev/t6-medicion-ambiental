// Presentación del contrato de sequías. El SPI y su categoría siempre vienen de Laravel.
export const categorias = {
    extremadamente_seco: ['Extremadamente seco', '#9a382d'], muy_seco: ['Muy seco', '#bc673b'],
    moderadamente_seco: ['Moderadamente seco', '#bd9842'], normal: ['Normal', '#547d6e'],
    moderadamente_humedo: ['Moderadamente húmedo', '#3c7c99'], muy_humedo: ['Muy húmedo', '#3c7c99'],
    extremadamente_humedo: ['Extremadamente húmedo', '#3c7c99'],
};
export const motivos = {
    BASE_INCOMPLETA: 'La base de referencia no tiene todos los años necesarios.',
    PERIODO_INCOMPLETO: 'Faltan datos en la ventana de tres meses.',
    BASE_NO_AJUSTABLE: 'La base no permite ajustar la distribución del indicador.',
    AJUSTE_FALLIDO: 'No se pudo obtener un ajuste estadístico válido.',
    PROBABILIDAD_EXTREMA: 'La probabilidad está fuera del intervalo numérico admitido.',
};
export const numero = (n, precision = 2) => Number.isFinite(n) ? new Intl.NumberFormat('es-BO', { maximumFractionDigits: precision }).format(n) : 'Sin dato';
export const categoria = dato => categorias[dato.spi.categoria]?.[0] ?? 'Sin índice';
export const fecha = valor => Number.isFinite(Date.parse(valor)) ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/La_Paz', hour12: false }).format(new Date(valor)) + ' · Bolivia' : 'No informada';
export const coordenada = valor => valor.map(n => numero(n, 6)).join(', ') + ' · longitud, latitud';
export const coordenadaValida = p => Array.isArray(p) && p.length === 2 && p.every(Number.isFinite) && Math.abs(p[0]) <= 180 && Math.abs(p[1]) <= 90;

export class ErrorConsulta extends Error {
    constructor(mensaje, codigo = 'CONEXION') { super(mensaje); this.codigo = codigo; }
}

export async function pedirJson(url, signal) {
    let respuesta;
    try { respuesta = await fetch(url, { signal, headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' }); }
    catch (e) { if (signal?.aborted) throw e; throw new ErrorConsulta('No se pudo conectar con GeoCruz. Revisa la conexión y reintenta.'); }
    let datos;
    try { datos = await respuesta.json(); }
    catch { throw new ErrorConsulta('El servidor no devolvió JSON legible.', 'RESPUESTA_INVALIDA'); }
    if (!respuesta.ok) throw new ErrorConsulta(datos.error?.mensaje ?? 'La consulta no está disponible.', datos.error?.codigo ?? 'ERROR_HTTP');
    return datos;
}

export function validarFiltros(f, catalogos, hoy = new Date()) {
    const inicioActual = hoy.toISOString().slice(0, 7);
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(f.mesFinal ?? '') || f.mesFinal < '2021-01' || f.mesFinal >= inicioActual) {
        throw new ErrorConsulta('Elige un mes completo desde enero de 2021, anterior al mes UTC actual.', 'FILTROS_INVALIDOS');
    }
    const resultado = { mesFinal: f.mesFinal };
    for (const campo of ['provincia', 'municipio', 'muestra']) if (f[campo]) resultado[campo] = f[campo];
    const territorios = catalogos.territorios;
    if (f.provincia && !territorios.some(t => t.identificador === f.provincia && t.tipo === 'provincia')) throw new ErrorConsulta('Provincia no disponible.', 'FILTROS_INVALIDOS');
    const municipio = territorios.find(t => t.identificador === f.municipio && t.tipo === 'municipio');
    if (f.municipio && (!municipio || (f.provincia && municipio.padre !== f.provincia))) throw new ErrorConsulta('El municipio no pertenece a la provincia elegida.', 'FILTROS_INCOMPATIBLES');
    const muestra = catalogos.muestras.find(m => m.identificador === f.muestra);
    if (f.muestra && (!muestra || (f.municipio && muestra.municipio !== f.municipio) || (f.provincia && muestra.provincia !== f.provincia))) throw new ErrorConsulta('La muestra no pertenece al territorio elegido.', 'FILTROS_INCOMPATIBLES');
    return resultado;
}

export function leerEnlace(busqueda, catalogos, hoy = new Date()) {
    const p = new URLSearchParams(busqueda);
    for (const campo of p.keys()) {
        if (!['mesFinal', 'provincia', 'municipio', 'muestra', 'seleccion'].includes(campo) || p.getAll(campo).length !== 1 || !p.get(campo)) throw new ErrorConsulta('El enlace contiene filtros desconocidos, repetidos o vacíos.', 'FILTROS_INVALIDOS');
    }
    if (!p.size) return null;
    const f = validarFiltros(Object.fromEntries(p), catalogos, hoy);
    const seleccion = p.get('seleccion');
    if (seleccion && !catalogos.muestras.some(m => m.identificador === seleccion)) throw new ErrorConsulta('La muestra del detalle no pertenece al catálogo.', 'FILTROS_INVALIDOS');
    return { filtros: f, seleccion };
}

export function comprobarRespuesta(r, f, ahora = Date.now()) {
    const fallo = () => { throw new ErrorConsulta('La respuesta no coincide con el contrato de sequías. No se muestran índices sin verificar.', 'CONTRATO_INCOMPATIBLE'); };
    if (r?.versionContrato !== 'sequias-1.0-m3-candidata' || !Array.isArray(r.datos) || !Array.isArray(r.fuentes) || !Array.isArray(r.advertencias)
        || r.consulta?.estado !== 'metodo_acordado' || r.consulta.mesFinal !== f.mesFinal || !r.consulta.referencia
        || r.consulta.obtencion?.esRespuestaHttp !== true || r.consulta.obtencion.tipo !== 'cache'
        || r.representacionMapa?.tipo !== 'Point' || r.representacionMapa.rellenarPoligonos !== false || r.representacionMapa.alcance !== 'conjunto'
        || r.paginacion?.aplica !== false || !r.resumen || !r.cobertura || !Array.isArray(r.cobertura.muestrasSinPreparar)) fallo();
    for (const campo of ['provincia', 'municipio', 'muestra']) if (f[campo] && r.consulta[campo] !== f[campo]) fallo();
    if (!f.muestra && r.consulta.muestra !== null) fallo();
    if (!f.provincia && !f.municipio && !f.muestra && (r.consulta.provincia !== null || r.consulta.municipio !== null)) fallo();
    const ids = new Set();
    for (const d of r.datos) {
        if (!d || typeof d.idMuestra !== 'string' || ids.has(d.idMuestra) || !coordenadaValida(d.coordenadaCelda) || !coordenadaValida(d.coordenadaSolicitada)
            || !d.spi || !Array.isArray(d.fuentesRef) || !d.fuentesRef.length || d.fuentesRef.some(id => !r.fuentes.some(s => s.id === id))
            || (d.precipitacionAcumuladaMm !== null && (!Number.isFinite(d.precipitacionAcumuladaMm) || d.precipitacionAcumuladaMm < 0))) fallo();
        if (d.spi.valor === null ? d.spi.categoria !== null || !motivos[d.spi.motivo] : !Number.isFinite(d.spi.valor) || !categorias[d.spi.categoria] || d.spi.motivo !== null) fallo();
        if ((f.municipio && d.territorioContexto !== f.municipio) || (f.muestra && d.idMuestra !== f.muestra)) fallo();
        ids.add(d.idMuestra);
    }
    const pendientes = r.cobertura.muestrasSinPreparar;
    if (pendientes.some(id => typeof id !== 'string' || ids.has(id)) || new Set(pendientes).size !== pendientes.length
        || r.resumen.totalMuestras !== r.datos.length || r.resumen.muestrasConIndice !== r.datos.filter(d => d.spi.valor !== null).length
        || r.resumen.muestrasSinIndice !== r.datos.filter(d => d.spi.valor === null).length
        || r.cobertura.muestrasSeleccionadas !== r.datos.length + pendientes.length || r.cobertura.superficieEvaluadaKm2 !== null) fallo();
    const vence = Date.parse(r.consulta.obtencion.vigenteHasta);
    if (!Number.isFinite(vence)) fallo();
    if (vence <= ahora) throw new ErrorConsulta('La publicación ha vencido. Debe renovarse antes de volver a consultarla.', 'PERIODO_NO_DISPONIBLE');
    return r;
}
