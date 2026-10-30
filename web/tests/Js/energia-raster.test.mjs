import test from 'node:test';
import assert from 'node:assert/strict';
import {decodificar, muestra, color} from '../../public/js/mapas/raster.js';

test('radiación solar conserva cero y W/m², sin convertirlos a índice UV', () => {
    assert.equal(decodificar('solar-wm2', 0, 0, 0, 255), 0);
    assert.equal(decodificar('solar-wm2', 255, 0, 0, 255), 1600);
    assert.equal(decodificar('solar-wm2', 255, 0, 0, 0), null);
    assert.notDeepEqual(color('radiacion_solar', 0), color('radiacion_solar', 800));
});

test('potencial energético conserva signo, cero exacto y faltantes', () => {
    assert.equal(decodificar('evap-pot-wm2', 0, 0, 0, 255), -500);
    assert.equal(decodificar('evap-pot-wm2', 64, 0, 0, 255), 0);
    assert.equal(decodificar('evap-pot-wm2', 255, 0, 0, 255), 1500);
    assert.equal(decodificar('evap-pot-wm2', 64, 0, 0, 0), null);
    const c = {ancho: 2, alto: 2, codificacion: 'evap-pot-wm2',
        rejilla: {oeste: -64, este: -63, sur: -18, norte: -17, filas: 'norte-sur', registro: 'nodos'}};
    const p = Uint8ClampedArray.from([64, 0, 0, 255, 64, 0, 0, 255, 64, 0, 0, 255, 64, 0, 0, 255]);
    assert.equal(muestra(c, p, -17.5, -63.5, true), 0);
    p[3] = 0;
    assert.equal(muestra(c, p, -17.5, -63.5, true), null);
});
