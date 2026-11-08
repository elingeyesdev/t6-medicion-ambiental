import {crearConsultaAgua, leerAgua} from './agua-contrato.js';

function errorConsulta(codigo = 'RESPUESTA_INVALIDA') {
    return Object.assign(new Error('No se pudo consultar.'), {codigo});
}

export async function cargarAgua(filtros, signal, peticion = globalThis.fetch) {
    const campos = ['territorio', 'referencia', 'comparacion'];
    if (!filtros || typeof filtros !== 'object' || Object.keys(filtros).length !== campos.length ||
        campos.some(campo => typeof filtros[campo] !== 'string' || !filtros[campo])) {
        throw errorConsulta('FILTROS_INVALIDOS');
    }
    const respuesta = await peticion('/api/v1/inundaciones/consulta?' + new URLSearchParams(
        campos.map(campo => [campo, filtros[campo]]),
    ), {signal, headers: {Accept: 'application/json'}, credentials: 'same-origin',
        mode: 'same-origin', redirect: 'error', cache: 'no-store'});
    const codigoHttp = {401: 'SESION_REQUERIDA', 403: 'ACCESO_DENEGADO', 429: 'LIMITE_CONSULTAS'}[respuesta.status];
    if (codigoHttp) throw errorConsulta(codigoHttp);
    const tipo = (respuesta.headers.get('content-type') ?? '').split(';')[0].trim();
    if (respuesta.redirected || !/^application\/(?:json|[a-z0-9!#$&^_.+-]+\+json)$/i.test(tipo)) {
        throw errorConsulta();
    }
    let dato;
    try { dato = await respuesta.json(); } catch { throw errorConsulta(); }
    if (!respuesta.ok) throw errorConsulta(dato?.error?.codigo);
    return leerAgua(dato);
}

export function crearConsultaAguaApi(mostrar, {peticion = globalThis.fetch} = {}) {
    return crearConsultaAgua((filtros, signal) => cargarAgua(filtros, signal, peticion), mostrar);
}
