import test, { after } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { ConsultaFocosWeb } from '../../public/js/focos-calor/consulta.js';
import { aLocal, validarPeriodoLocal, ErrorApi } from '../../public/js/focos-calor/contrato.js';

// Replay controlado de la misma captura histórica usada por Flutter. No llama a NASA.
const seed = JSON.parse(readFileSync(new URL('../../../docs/evidencias/api01/consulta_real.json', import.meta.url)));
const catalog = JSON.parse(readFileSync(new URL('../../../compartido/territorios/datos/catalogo.json', import.meta.url)));
const geometry = JSON.parse(readFileSync(new URL('../../../compartido/territorios/datos/limites/departamentos.geojson', import.meta.url)));
const boundary = { territorio: seed.consulta.territorio, procedencia: { version: seed.consulta.territorio.versionCatalogo }, limite: geometry.features[0] };
const filters = { desde: seed.consulta.periodo.desde, hasta: seed.consulta.periodo.hasta, modo: 'externo' };
const results = { cliente: 'web', origen: 'replay_controlado', casos: [], equivalencia: null };
const page = (number = 1) => {
    const r = structuredClone(seed);
    r.datos = r.datos.slice((number - 1) * 25, number * 25);
    r.paginacion = { ...r.paginacion, pagina: number, tamanioPagina: 25, siguiente: number < Math.ceil(r.paginacion.total / 25) ? number + 1 : null };
    return r;
};
const empty = coverage => {
    const r = page();
    r.datos = [];
    r.paginacion = { ...r.paginacion, total: 0, siguiente: null };
    r.resumen = { ...r.resumen, totalRegistros: 0, registrosValidos: 0, registrosConFaltantes: 0, indicadores: [] };
    r.cobertura = { ...r.cobertura, estado: coverage, fundamento: 'Condición controlada de QA-01; no observación externa.' };
    return r;
};
const create = provider => new ConsultaFocosWeb('/api/v1', () => {}, async url => url.includes('/limite') ? boundary : provider(new URL(url, 'http://prueba')));
const recordCase = (id, execute) => test(`QA21 ${id}`, async () => { await execute(); results.casos.push(id); });

recordCase('equivalencia', async () => {
    const vm = create(async () => page());
    await vm.consultar(filters);
    assert.equal(vm.estado.fase, 'listo');
    const r = vm.estado.actual.respuesta;
    assert.equal(r.datos.length, 25);
    results.equivalencia = {
        datos: r.datos.map(x => ({ identificador: x.identificador, atributos: x.atributos, fechas: x.fechas, territorio: x.territorio, faltantes: x.faltantes, fuentesRef: x.fuentesRef, naturaleza: x.naturaleza, metodo: x.metodo, coordenadas: x.geometria?.coordinates ?? null })),
        consulta: r.consulta, fuentes: r.fuentes, resumen: r.resumen, cobertura: r.cobertura, advertencias: r.advertencias, paginacion: r.paginacion,
    };
});

recordCase('paginacion', async () => {
    const calls = [];
    const vm = create(async u => { calls.push(u); return page(Number(u.searchParams.get('pagina'))); });
    await vm.consultar(filters);
    const first = vm.estado.actual.respuesta;
    await vm.paginar(2);
    assert.equal(vm.estado.actual.respuesta.paginacion.pagina, 2);
    assert.deepEqual(vm.estado.actual.respuesta.datos.map(x => x.identificador), seed.datos.slice(25, 50).map(x => x.identificador));
    assert.deepEqual(vm.estado.actual.respuesta.resumen, first.resumen);
    assert.equal(calls[1].searchParams.get('versionConjunto'), first.paginacion.versionConjunto);
});

for (const [id, coverage] of [['vacio', 'desconocida'], ['sin_cobertura', 'sin_cobertura'], ['parcial', 'parcial']]) {
    recordCase(id, async () => {
        const vm = create(async () => empty(coverage));
        await vm.consultar(filters);
        assert.equal(vm.estado.fase, 'listo');
        assert.equal(vm.estado.actual.respuesta.cobertura.estado, coverage);
        assert.deepEqual(vm.estado.actual.respuesta.datos, []);
        assert.equal(vm.estado.actual.respuesta.paginacion.total, 0);
    });
}

recordCase('fallo_reintento', async () => {
    let fail = false;
    const vm = create(async () => { if (fail) throw new ErrorApi('Fallo controlado', 'PROVEEDOR_NO_DISPONIBLE', 503); return page(); });
    await vm.consultar(filters);
    const previous = vm.estado.actual;
    fail = true;
    await vm.consultar(filters);
    assert.equal(vm.estado.fase, 'error');
    assert.equal(vm.estado.actual, previous);
    assert.equal(vm.estado.error.estado, 503);
    fail = false;
    await vm.reintentar();
    assert.equal(vm.estado.fase, 'listo');
});

recordCase('expiracion', async () => {
    const calls = [];
    const vm = create(async u => { calls.push(u); if (u.searchParams.has('versionConjunto')) throw new ErrorApi('Expirado', 'CONJUNTO_CAMBIADO', 409); return page(); });
    await vm.consultar(filters);
    const previous = vm.estado.actual;
    await vm.paginar(2);
    assert.equal(vm.estado.error.estado, 409);
    assert.equal(vm.estado.actual, previous);
    await vm.reiniciar();
    assert.equal(calls.at(-1).searchParams.has('versionConjunto'), false);
    assert.equal(vm.estado.fase, 'listo');
});

recordCase('respuesta_antigua', async () => {
    const deferred = [];
    const vm = create(() => new Promise(resolve => deferred.push(resolve)));
    const old = vm.consultar(filters);
    const recent = vm.consultar(filters);
    const newer = page(); newer.consulta.identificador = 'consulta-control-mas-reciente';
    deferred[1](newer); await recent;
    const current = vm.estado.actual;
    deferred[0](page()); await old;
    assert.equal(vm.estado.actual, current);
    assert.equal(current.respuesta.consulta.identificador, 'consulta-control-mas-reciente');
});

recordCase('periodo_invalido', async () => {
    const start = aLocal(filters.desde);
    assert.throws(() => validarPeriodoLocal(start, start));
    assert.throws(() => validarPeriodoLocal('2026-10-01T00:00', '2026-10-08T00:00:01'));
    assert.throws(() => validarPeriodoLocal('2026-10-02T00:00', '2026-10-01T00:00'));
});

recordCase('territorio_incompatible', async () => {
    assert.ok(catalog.territorios.some(t => t.identificador === 'BO070101' && t.padre === 'BO0701'));
    const vm = create(async u => {
        assert.equal(u.searchParams.get('provincia'), 'BO0702');
        assert.equal(u.searchParams.get('municipio'), 'BO070101');
        throw new ErrorApi('Municipio incompatible', 'FILTROS_INCOMPATIBLES', 422);
    });
    await vm.consultar({ ...filters, provincia: 'BO0702', municipio: 'BO070101' });
    assert.equal(vm.estado.error.estado, 422);
    assert.equal(vm.estado.actual, null);
});

recordCase('cero_faltante', async () => {
    const value = page();
    value.datos[0].atributos.potenciaRadiativa = 0;
    value.datos[1].atributos.potenciaRadiativa = null;
    value.datos[1].faltantes = [{ campo: 'potenciaRadiativa', motivo: 'Prueba controlada' }];
    const vm = create(async () => value);
    await vm.consultar(filters);
    const data = vm.estado.actual.respuesta.datos;
    assert.equal(data[0].atributos.potenciaRadiativa, 0);
    assert.equal(data[1].atributos.potenciaRadiativa, null);
    assert.equal(data[1].faltantes.length, 1);
});

after(() => {
    const dir = new URL('../../../tmp/qa21/', import.meta.url);
    mkdirSync(dir, { recursive: true });
    writeFileSync(new URL('web.json', dir), JSON.stringify(results, null, 2) + '\n');
});
