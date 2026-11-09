import {crearConsultaAgua, leerAgua} from './agua-contrato.js';

function errorConsulta(codigo = 'RESPUESTA_INVALIDA') {
    return Object.assign(new Error('No se pudo consultar.'), {codigo});
}

async function pedirJson(url, signal, peticion) {
    const respuesta = await peticion(url, {
        signal, headers: {Accept:'application/json'}, credentials:'same-origin',
        mode:'same-origin', redirect:'error', cache:'no-store',
    });
    const codigo = {401:'SESION_REQUERIDA',403:'ACCESO_DENEGADO',429:'LIMITE_CONSULTAS'}[respuesta.status];
    if (codigo) throw errorConsulta(codigo);
    const tipo = (respuesta.headers.get('content-type') ?? '').split(';')[0].trim();
    if (respuesta.redirected || !/^application\/(?:json|[a-z0-9!#$&^_.+-]+\+json)$/i.test(tipo)) throw errorConsulta();
    let dato;
    try { dato = await respuesta.json(); } catch { throw errorConsulta(); }
    if (!respuesta.ok) throw errorConsulta(dato?.error?.codigo);
    return {dato,respuesta};
}

function fechaValida(fecha) {
    if (typeof fecha !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(fecha)) return false;
    const instante=new Date(fecha+'T00:00:00Z');
    return Number.isFinite(instante.valueOf()) && instante.toISOString().slice(0,10)===fecha;
}

export function leerCatalogoAgua(dato) {
    if (dato?.versionContrato!=='agua-0.1-candidata' || !Array.isArray(dato.productos) || dato.productos.length>20 ||
        dato.estado!==(dato.productos.length ? 'disponible' : 'sin_productos_validados')) throw errorConsulta();
    const ids=new Set(), identidades=new Set();
    for (const producto of dato.productos) {
        const t=producto.territorio, c=producto.comparacion;
        if (typeof producto.id!=='string' || !/^[a-f0-9]{64}$/.test(producto.id) || ids.has(producto.id) ||
            typeof t?.identificador!=='string' || !/^BO07(?:[0-9]{2}){0,2}$/.test(t.identificador) ||
            typeof t?.nombre!=='string' || !t.nombre.trim() || t.nombre.length>200 || t.alcance!=='recorte_parcial' ||
            !fechaValida(c?.referencia) || !fechaValida(c?.comparacion) || c.referencia>=c.comparacion ||
            !['cambio_agua_observado','sin_cambios','sin_datos'].includes(producto.estado)) throw errorConsulta();
        const identidad=JSON.stringify([t.identificador,c.referencia,c.comparacion]);
        if (identidades.has(identidad)) throw errorConsulta();
        ids.add(producto.id);identidades.add(identidad);
    }
    return dato;
}

export async function cargarCatalogoAgua(signal, peticion=globalThis.fetch) {
    const {dato}=await pedirJson('/mapa/agua/catalogo',signal,peticion);
    return leerCatalogoAgua(dato);
}

export async function cargarAgua(filtros, signal, peticion=globalThis.fetch, versionEsperada=null) {
    const campos=['territorio','referencia','comparacion'];
    if (!filtros || typeof filtros!=='object' || Object.keys(filtros).length!==campos.length ||
        campos.some(campo=>typeof filtros[campo]!=='string' || !filtros[campo])) throw errorConsulta('FILTROS_INVALIDOS');
    const {dato,respuesta}=await pedirJson('/mapa/agua/consulta?'+new URLSearchParams(
        campos.map(campo=>[campo,filtros[campo]])),signal,peticion);
    leerAgua(dato);
    if (dato.territorio.identificador!==filtros.territorio || dato.comparacion.referencia!==filtros.referencia ||
        dato.comparacion.comparacion!==filtros.comparacion) throw errorConsulta();
    if (versionEsperada!==null && respuesta.headers.get('X-Producto-SHA256')!==versionEsperada) throw errorConsulta('CATALOGO_CAMBIO');
    return dato;
}

export function crearConsultaAguaApi(mostrar, {peticion=globalThis.fetch}={}) {
    return crearConsultaAgua((filtros,signal)=>cargarAgua(filtros,signal,peticion),mostrar);
}
