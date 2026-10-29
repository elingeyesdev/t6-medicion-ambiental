import test from 'node:test';
import assert from 'node:assert/strict';
import { puntosContinuos } from '../../public/js/mapas/mapa-continua.js';

const p = (codigo, valor = 0) => ({codigo, nombre: codigo, latitud: -17, longitud: -63, valores: {temperatura: valor}});
test('la simulación solo dibuja los puntos de la selección territorial aceptada', () => {
    const e = {origen: 'simulado', puntos: [p('uno'), p('dos', 30)]};
    assert.deepEqual(puntosContinuos(e, {datos: [p('dos')]}, 'temperatura').map(p => p.codigo), ['dos']);
    assert.equal(puntosContinuos(e, null, 'temperatura').length, 0);
    assert.equal(puntosContinuos({...e, origen: 'observado'}, {datos: e.puntos}, 'temperatura').length, 0);
});
test('cero se conserva, un faltante no se inventa y la actualización reemplaza el valor', () => {
    const consulta = {datos: [p('uno')]};
    assert.equal(puntosContinuos({origen: 'simulado', puntos: [p('uno')]}, consulta, 'temperatura')[0].valor, 0);
    assert.equal(puntosContinuos({origen: 'simulado', puntos: [p('uno', 28)]}, consulta, 'temperatura')[0].valor, 28);
    assert.equal(puntosContinuos({origen: 'simulado', puntos: [p('uno', null)]}, consulta, 'temperatura')[0].valor, null);
    assert.equal(puntosContinuos({origen: 'simulado', puntos: [{...p('uno'), latitud: 100}]}, consulta, 'temperatura').length, 0);
});
