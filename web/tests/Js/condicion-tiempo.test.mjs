import test from 'node:test';
import assert from 'node:assert/strict';
import {condicionTiempo} from '../../public/js/mapas/condicion-tiempo.js';

test('código cero conserva el cielo despejado y la noche cambia únicamente su símbolo', () => {
    assert.deepEqual(condicionTiempo(0, true), {nombre: 'Despejado', icono: 'sol'});
    assert.deepEqual(condicionTiempo(0, false), {nombre: 'Despejado', icono: 'luna'});
    assert.equal(condicionTiempo(2, false).icono, 'nube-luna');
    assert.equal(condicionTiempo(2, true).icono, 'nube-sol');
    assert.equal(condicionTiempo(61, false).nombre, 'Lluvia ligera');
});

test('niebla, hielo, nieve y tormentas mantienen sus categorías y descripciones', () => {
    for (const [codigo, nombre, icono] of [[45, 'Niebla', 'niebla'], [48, 'Niebla con escarcha', 'niebla'],
        [57, 'Llovizna helada densa', 'lluvia-helada'], [67, 'Lluvia helada fuerte', 'lluvia-helada'],
        [77, 'Nieve granulada', 'nieve'], [95, 'Tormenta eléctrica', 'tormenta'], [97, 'Tormenta fuerte', 'tormenta'],
        [99, 'Tormenta con granizo fuerte', 'granizo']]) assert.deepEqual(condicionTiempo(codigo), {nombre, icono});
});

test('no clasifica faltantes, cadenas, booleanos o códigos desconocidos como sol o tormenta', () => {
    for (const codigo of [null, undefined, false, true, '0', '<svg>', NaN, Infinity, 1.5, -1, 69, 100, {}, []]) {
        assert.deepEqual(condicionTiempo(codigo), {nombre: 'Sin dato', icono: 'sin-dato'});
    }
});
