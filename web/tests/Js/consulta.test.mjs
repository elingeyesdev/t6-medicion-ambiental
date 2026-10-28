import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { ConsultaFocosWeb } from '../../public/js/focos-calor/consulta.js';
import { aUtc, aLocal, numero, solicitarJson, comprobarRespuesta, validarInstanteUrl, ErrorApi } from '../../public/js/focos-calor/contrato.js';

// Respuestas congeladas para pruebas controladas: ninguna solicitud a NASA o a teselas.
const semilla = JSON.parse(readFileSync(new URL('../../../docs/evidencias/api01/consulta_real.json', import.meta.url)));
const departamento = JSON.parse(readFileSync(new URL('../../../compartido/territorios/datos/limites/departamentos.geojson', import.meta.url)));
const limite = { limite: departamento.features[0], territorio: semilla.consulta.territorio, procedencia: { version: semilla.consulta.territorio.versionCatalogo } };
const filtros = { desde: '2026-10-10T00:00:00Z', hasta: '2026-10-11T00:00:00Z', modo: 'externo' };
const respuesta = (pagina = 1) => {
    const r = structuredClone(semilla);
    r.datos = r.datos.slice((pagina - 1) * 25, pagina * 25);
    r.paginacion = { ...r.paginacion, pagina, tamanioPagina: 25, siguiente: pagina < 3 ? pagina + 1 : null };
    return r;
};
const crear = pedir => {
    const eventos = [];
    const control = new ConsultaFocosWeb('/api/v1', e => eventos.push(e), pedir);
    return { control, eventos };
};

test('Bolivia se convierte a UTC sin depender de la zona horaria del PC', () => {
    assert.equal(aUtc('2026-10-10T00:00'), '2026-10-10T04:00:00Z');
    assert.equal(aLocal('2026-10-10T00:00:00Z'), '2026-10-09T20:00:00');
    assert.equal(aUtc('2024-02-29T23:59:59'), '2024-03-01T03:59:59Z');
    for (const f of ['2026-02-30T00:00', '2026-10-10T24:00', '2026-10-10', '']) assert.throws(() => aUtc(f));
});

test('los enlaces no normalizan silenciosamente fechas imposibles o sin zona', () => {
    assert.equal(validarInstanteUrl('2026-10-10T00:00:00-04:00'), '2026-10-10T00:00:00-04:00');
    for (const f of ['2026-02-30T00:00:00Z', '2026-10-10T24:00:00Z', '2026-10-10T00:00:00', '2026-10-10T00:00:00+14:01']) assert.throws(() => validarInstanteUrl(f));
});

test('FRP cero se distingue de ausencia de dato', () => {
    assert.equal(numero(0, 'MW'), '0 MW');
    assert.equal(numero(null, 'MW'), 'Sin dato');
    assert.equal(numero(undefined), 'Sin dato');
});

test('el catálogo de respuestas se valida antes de publicar datos', () => {
    assert.equal(comprobarRespuesta(respuesta(), 1).paginacion.total, 60);
    const mala = respuesta(); mala.resumen.totalRegistros = 1;
    assert.throws(() => comprobarRespuesta(mala, 1), { codigo: 'CONTRATO_INCOMPATIBLE' });
    const nula = respuesta(); nula.datos = [null];
    assert.throws(() => comprobarRespuesta(nula, 1), { codigo: 'CONTRATO_INCOMPATIBLE' });
});

test('el transporte convierte HTML y fallos HTTP en errores, sin resultados ficticios', async () => {
    await assert.rejects(solicitarJson('/api', undefined, async () => ({ ok: false, status: 503, json: async () => ({ error: { codigo: 'PROVEEDOR_NO_DISPONIBLE', mensaje: 'Proveedor no disponible' } }) })), { codigo: 'PROVEEDOR_NO_DISPONIBLE', estado: 503 });
    await assert.rejects(solicitarJson('/api', undefined, async () => ({ ok: true, status: 200, json: async () => { throw new Error('HTML privado'); } })), { codigo: 'RESPUESTA_INVALIDA' });
});

test('carga inicial y resultado publican estado y fuente del mismo contrato', async () => {
    const { control, eventos } = crear(async url => url.includes('/limite') ? limite : respuesta());
    await control.consultar(filtros);
    assert.equal(eventos[0].fase, 'cargando');
    assert.equal(control.estado.fase, 'listo');
    assert.equal(control.estado.actual.respuesta.resumen.totalRegistros, 60);
    assert.deepEqual(control.estado.actual.filtros, filtros);
});

test('páginas conservan token, filtros, total y resumen; no solicitan otro límite', async () => {
    const urls = [];
    const { control } = crear(async url => { urls.push(url); return url.includes('/limite') ? limite : respuesta(Number(new URL(url, 'http://local').searchParams.get('pagina'))); });
    await control.consultar(filtros);
    await control.paginar(2);
    assert.equal(control.estado.actual.respuesta.paginacion.pagina, 2);
    assert.equal(control.estado.actual.respuesta.resumen.totalRegistros, 60);
    assert.equal(control.estado.actual.respuesta.datos.length, 25);
    assert.ok(urls[2].includes(`versionConjunto=${semilla.paginacion.versionConjunto}`));
    assert.equal(urls.filter(u => u.includes('/limite')).length, 1);
    assert.equal(new URL(urls[2], 'http://local').searchParams.get('desde'), filtros.desde);
});

test('una respuesta atrasada no sustituye una consulta más reciente', async () => {
    const pendientes = [];
    const { control } = crear((url, signal) => url.includes('/limite') ? Promise.resolve(limite) : new Promise(resolve => pendientes.push({ resolve, signal })));
    const vieja = control.consultar(filtros);
    const nueva = control.consultar({ ...filtros, confianza: 'alta' });
    assert.equal(pendientes[0].signal.aborted, true);
    pendientes[1].resolve(respuesta()); await nueva;
    const actual = control.estado.actual;
    pendientes[0].resolve(respuesta()); await vieja;
    assert.equal(control.estado.actual, actual);
    assert.equal(control.estado.actual.filtros.confianza, 'alta');
});

test('editar filtros cancela la petición y preserva el conjunto anterior etiquetable', async () => {
    let resolver;
    let llamadas = 0;
    const { control } = crear(url => url.includes('/limite') ? Promise.resolve(limite) : (++llamadas === 1 ? Promise.resolve(respuesta()) : new Promise(r => { resolver = r; })));
    await control.consultar(filtros);
    const actual = control.estado.actual;
    const trabajo = control.consultar({ ...filtros, confianza: 'alta' });
    control.editar(); resolver(respuesta()); await trabajo;
    assert.equal(control.estado.fase, 'anterior');
    assert.equal(control.estado.actual, actual);
    assert.equal(control.estado.borrador, true);
    control.restaurar(); assert.equal(control.estado.fase, 'listo');
});

test('un fallo conserva los datos anteriores y reintenta la solicitud pendiente', async () => {
    let fallar = false;
    const { control } = crear(async url => { if (url.includes('/limite')) return limite; if (fallar) throw new ErrorApi('NASA no disponible', 'PROVEEDOR_NO_DISPONIBLE', 503); return respuesta(); });
    await control.consultar(filtros);
    const actual = control.estado.actual;
    fallar = true; await control.consultar({ ...filtros, confianza: 'alta' });
    assert.equal(control.estado.fase, 'error'); assert.equal(control.estado.actual, actual);
    assert.equal(control.estado.pendiente.filtros.confianza, 'alta');
    fallar = false; await control.reintentar();
    assert.equal(control.estado.fase, 'listo'); assert.equal(control.estado.actual.filtros.confianza, 'alta');
});

test('un 409 mantiene el resultado anterior y reinicia sin arrastrar el token', async () => {
    const urls = [];
    const { control } = crear(async url => { urls.push(url); if (url.includes('/limite')) return limite; if (url.includes('versionConjunto')) throw new ErrorApi('Expirado', 'CONJUNTO_CAMBIADO', 409); return respuesta(); });
    await control.consultar(filtros); const actual = control.estado.actual;
    await control.paginar(2);
    assert.equal(control.estado.error.codigo, 'CONJUNTO_CAMBIADO'); assert.equal(control.estado.actual, actual);
    await control.reiniciar(); assert.equal(control.estado.fase, 'listo'); assert.equal(urls.at(-1).includes('versionConjunto'), false);
});

test('el fallo cartográfico conserva los resultados y el acceso textual', async () => {
    const { control } = crear(async url => { if (url.includes('/limite')) throw new ErrorApi('Límite inaccesible'); return respuesta(); });
    await control.consultar(filtros);
    assert.equal(control.estado.fase, 'listo'); assert.equal(control.estado.actual.respuesta.datos.length, 25);
    assert.equal(control.estado.actual.limite, null); assert.match(control.estado.actual.errorMapa, /listado/);
});

test('vacío, parcial y sin cobertura no se convierten en errores o datos simulados', async () => {
    for (const cobertura of ['desconocida', 'parcial', 'sin_cobertura']) {
        const r = respuesta(); r.datos = []; r.resumen.totalRegistros = 0; r.paginacion.total = 0; r.paginacion.siguiente = null; r.cobertura.estado = cobertura;
        const { control } = crear(async url => url.includes('/limite') ? limite : r);
        await control.consultar(filtros);
        assert.equal(control.estado.fase, 'listo'); assert.equal(control.estado.actual.respuesta.cobertura.estado, cobertura); assert.deepEqual(control.estado.actual.respuesta.datos, []);
    }
});

test('un resumen cambiado entre páginas se rechaza antes de mezclar conjuntos', async () => {
    const { control } = crear(async url => { if (url.includes('/limite')) return limite; const r = respuesta(url.includes('pagina=2') ? 2 : 1); if (r.paginacion.pagina === 2) r.resumen.indicadores[0].valor = 999; return r; });
    await control.consultar(filtros); const actual = control.estado.actual;
    await control.paginar(2);
    assert.equal(control.estado.error.codigo, 'CONJUNTO_CAMBIADO'); assert.equal(control.estado.actual, actual);
});
