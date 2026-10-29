import test from 'node:test';
import assert from 'node:assert/strict';
import { compararLecturas } from '../../public/js/mapas/historial-mapa.js';
import { consultaMonitoreo } from '../../public/js/mapas/consulta-monitoreo.js';

const lectura = (valor, extra = {}) => ({valor, origen: 'observado', fuente: 'manual', instante: '2026-10-28T08:00:00Z', latitud: 0, longitud: 0, ...extra});
const punto = lecturas => ({metrica: 'temperatura', unidad: '°C', lecturas});

test('compara valores cero sin tratarlos como datos faltantes', () => {
    assert.equal(compararLecturas(punto([lectura(0)]), punto([lectura(5)]))[0].cambio, 5);
    assert.equal(compararLecturas(punto([lectura(0)]), punto([lectura(0)]))[0].cambio, 0);
});
test('una lectura ausente produce un faltante, sin inventar diferencia cero', () => {
    const filas = compararLecturas(punto([lectura(0)]), punto([]));
    assert.equal(filas[0].b, null); assert.equal(filas[0].cambio, null);
    assert.deepEqual(compararLecturas(punto([]), punto([])), []);
});
test('los simulados no se comparan con registros de la misma fuente', () => {
    const filas = compararLecturas(punto([lectura(10)]), punto([lectura(40, {origen: 'simulado'})]));
    assert.equal(filas.length, 2); assert.ok(filas.every(f => f.cambio === null));
});
test('proveedores distintos mantienen sus valores separados', () => {
    const filas = compararLecturas(punto([lectura(10, {fuente: 'a'})]), punto([lectura(20, {fuente: 'b'})]));
    assert.equal(filas.length, 2); assert.ok(filas.every(f => f.cambio === null));
});
test('rechaza comparar variables o unidades distintas', () => {
    assert.equal(compararLecturas(punto([lectura(1)]), {...punto([lectura(2)]), metrica: 'humedad'}), null);
    assert.equal(compararLecturas(punto([lectura(1)]), {...punto([lectura(2)]), unidad: '°F'}), null);
});
test('conserva ambas fechas y coordenadas sin modificar las lecturas', () => {
    const a = Object.freeze(lectura(0)), b = Object.freeze(lectura(5, {instante: '2026-10-28T10:00:00Z', latitud: -17}));
    const fila = compararLecturas(punto([a]), punto([b]))[0];
    assert.equal(fila.a.latitud, 0); assert.equal(fila.b.latitud, -17);
    assert.notEqual(fila.a.instante, fila.b.instante);
});
test('la diferencia conserva decimales sin residuos binarios', () => {
    assert.equal(compararLecturas(punto([lectura(0.1)]), punto([lectura(0.3)]))[0].cambio, 0.2);
});
const valores = {desde: '2026-10-28T00:00', hasta: '2026-10-28T12:00', metrica: 'temperatura', por_momento: 'on', momento: '2026-10-28T04:00'};
const ahora = Date.parse('2026-10-28T18:00:00Z');
test('el momento se convierte desde Bolivia a UTC y permite el inicio exacto', () => {
    assert.equal(consultaMonitoreo(valores, ahora).get('momento'), '2026-10-28T08:00:00Z');
    assert.equal(consultaMonitoreo({...valores, momento: valores.desde}, ahora).get('momento'), '2026-10-28T04:00:00Z');
});
test('rechaza un momento anterior al inicio, igual al final o futuro', () => {
    for (const momento of ['2026-10-27T23:59', '2026-10-28T12:00', '2026-10-29T12:00']) {
        assert.throws(() => consultaMonitoreo({...valores, momento}, ahora), /momento/);
    }
});
test('desactivar el recorrido restaura la última lectura del período', () => {
    assert.equal(consultaMonitoreo({...valores, por_momento: undefined}, ahora).has('momento'), false);
});
