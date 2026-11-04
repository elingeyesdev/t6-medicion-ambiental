import test from 'node:test';
import assert from 'node:assert/strict';
import {conservarComparacion, hayFechasNuevas, vigilarCatalogo} from '../../public/js/mapas/actualizacion-catalogo.js';

test('nuevas fechas conservan A y B aunque una imagen elegida pase al archivo', () => {
    const anterior = [{id: 'temperatura', cuadros: [{id: 't-antigua'}, {id: 't-actual'}]}, {id: 'viento', cuadros: [{id: 'v-antigua'}]}];
    const nuevo = [{id: 'temperatura', cuadros: [{id: 't-actual'}, {id: 't-nueva'}]}, {id: 'viento', cuadros: [{id: 'v-nueva'}]}];
    const copia = structuredClone(nuevo);
    const resultado = conservarComparacion(anterior, nuevo, [{capa: 'temperatura', fecha: 't-antigua'}, {capa: 'viento', fecha: 'v-antigua'}]);
    assert.deepEqual(resultado[0].cuadros.map(c => c.id), ['t-actual', 't-nueva', 't-antigua']);
    assert.deepEqual(resultado[1].cuadros.map(c => c.id), ['v-nueva', 'v-antigua']);
    assert.deepEqual(nuevo, copia);
    assert.equal(conservarComparacion(anterior, nuevo, [{capa: 'temperatura', fecha: 't-actual'}])[0].cuadros.length, 2);
    assert.equal(hayFechasNuevas(anterior, nuevo), true);
    assert.equal(hayFechasNuevas(anterior, [{id: 'temperatura', cuadros: [{id: 't-actual'}]}]), false);
});

test('la vigilancia no se solapa, respeta la visibilidad y descarta respuestas al salir', async () => {
    let activo = false, resolver, peticiones = 0, intervalo, cancelado, signal;
    const recibidos = [];
    const vigilancia = vigilarCatalogo({
        consultar: s => { signal = s; peticiones++; return new Promise(r => { resolver = r; }); },
        activa: () => activo, recibir: datos => recibidos.push(datos),
        programar: (f, ms) => { intervalo = ms; return 99; }, cancelar: id => { cancelado = id; },
    });
    await vigilancia.comprobar();
    assert.equal(peticiones, 0);
    activo = true;
    const consulta = vigilancia.comprobar();
    await vigilancia.comprobar();
    assert.equal(peticiones, 1);
    vigilancia.detener();
    resolver({revision: 'nueva'});
    await consulta;
    assert.deepEqual(recibidos, []);
    assert.equal(signal.aborted, true);
    assert.equal(intervalo, 60000);
    assert.equal(cancelado, 99);
});

test('un fallo temporal de vigilancia permite la siguiente consulta', async () => {
    let veces = 0;
    const recibidos = [];
    const vigilancia = vigilarCatalogo({
        consultar: async () => { if (++veces === 1) throw new Error('sin red'); return {revision: 'valida'}; },
        activa: () => true, recibir: datos => recibidos.push(datos), programar: () => 1, cancelar() {},
    });
    await vigilancia.comprobar(); await vigilancia.comprobar(); vigilancia.detener();
    assert.deepEqual(recibidos, [{revision: 'valida'}]);
});

test('volver desde el historial reanuda la vigilancia sin aceptar la respuesta anterior', async () => {
    let terminar, veces = 0;
    const recibidos = [];
    const vigilancia = vigilarCatalogo({
        consultar: async () => { if (++veces === 1) return new Promise(r => { terminar = r; }); return {revision: 'actual'}; },
        activa: () => true, recibir: datos => recibidos.push(datos), programar: () => 1, cancelar() {},
    });
    const previa = vigilancia.comprobar();
    vigilancia.detener(); vigilancia.reanudar(); terminar({revision: 'antigua'});
    await previa;
    await vigilancia.comprobar(); vigilancia.detener();
    assert.deepEqual(recibidos, [{revision: 'actual'}]);
});
