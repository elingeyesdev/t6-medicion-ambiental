import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {crearPresentador} from '../../public/js/datos/unidades.js';

const catalogo = JSON.parse(readFileSync(new URL('../../../compartido/unidades/presentacion.json', import.meta.url))).grupos;
const crear = preferencias => crearPresentador({catalogo, preferencias});
const cerca = (a, b) => assert.ok(Math.abs(a - b) < 1e-9, `${a} != ${b}`);

test('temperaturas conocidas, cero y negativos', () => {
    const p = crear({temperatura: 'F'});
    assert.equal(p.numero('temperatura', 0, '°C'), 32);
    assert.equal(p.numero('temperatura', -40, '°C'), -40);
    assert.equal(p.numero('temperatura', 100, '°C'), 212);
    assert.equal(p.unidad('temperatura', '°C'), '°F');
    p.establecer({temperatura: 'K'});
    cerca(p.numero('temperatura', 0, '°C'), 273.15);
    cerca(p.numero('temperatura', -273.15, '°C'), 0);
});

test('las diferencias no reciben desplazamiento de temperatura', () => {
    for (const unidad of ['C', 'F', 'K']) {
        const p = crear({temperatura: unidad});
        const cambio = p.numero('temperatura', 2, '°C', true);
        cerca(cambio, p.numero('temperatura', 22, '°C') - p.numero('temperatura', 20, '°C'));
        assert.equal(p.numero('temperatura', 0, '°C', true), 0);
    }
});

test('viento con milla internacional y metros por segundo', () => {
    const p = crear({viento: 'm/s'});
    assert.equal(p.numero('viento', 36, 'km/h'), 10);
    assert.equal(p.numero('viento', 0, 'km/h'), 0);
    p.establecer({viento: 'mph'});
    cerca(p.numero('viento', 16.09344, 'km/h'), 10);
});

test('no convierte faltantes o cadenas en cero ni en temperatura real', () => {
    const p = crear({temperatura: 'F'});
    for (const valor of [null, undefined, NaN, Infinity, -Infinity, '', '0', false, []]) {
        assert.equal(p.numero('temperatura', valor, '°C'), null);
        assert.equal(p.texto('temperatura', valor, '°C'), 'No disponible');
    }
    assert.equal(p.texto('temperatura', 0, '°C'), '32 °F');
});

test('alias de temperatura y velocidad del original', () => {
    const p = crear({temperatura: 'F', viento: 'm/s'});
    for (const clave of ['temperatura', 'sensacion', 'maxima', 'minima']) assert.equal(p.numero(clave, 0, '°C'), 32);
    assert.equal(p.numero('windSpeed', 36, 'km/h'), 10);
});

test('no convierte índices en concentraciones ni otras magnitudes', () => {
    const p = crear({temperatura: 'F', viento: 'mph', aqi: 'PM25'});
    for (const [clave, unidad] of [['aqi', 'AQI'], ['humedad', '%'], ['lluvia', 'mm'], ['ica', 'ICA'], ['ruido', 'dB']]) {
        assert.equal(p.numero(clave, 20, unidad), 20);
        assert.equal(p.unidad(clave, unidad), unidad);
    }
});

test('una unidad base diferente se conserva para evitar convertir dos veces', () => {
    const p = crear({temperatura: 'F', viento: 'm/s'});
    assert.equal(p.numero('temperatura', 32, '°F'), 32);
    assert.equal(p.numero('viento', 10, 'm/s'), 10);
});

test('preferencia ausente o desconocida usa la unidad predeterminada', () => {
    assert.equal(crear({}).numero('temperatura', 0, '°C'), 0);
    assert.equal(crear({temperatura: 'inventada'}).unidad('temperatura', '°C'), '°C');
});

test('actualizar preferencias no modifica la lectura ni el catálogo', () => {
    const lectura = {valor: 20, unidad: '°C'};
    const copia = JSON.stringify(catalogo);
    const p = crear({temperatura: 'F'});
    assert.equal(p.numero('temperatura', lectura.valor, lectura.unidad), 68);
    p.establecer({temperatura: 'C'});
    assert.equal(p.numero('temperatura', lectura.valor, lectura.unidad), 20);
    assert.deepEqual(lectura, {valor: 20, unidad: '°C'});
    assert.equal(JSON.stringify(catalogo), copia);
});

test('el redondeo es solo de presentación y permite comparar después', () => {
    const p = crear({temperatura: 'F'});
    cerca(p.numero('temperatura', 20.01, '°C'), 68.018);
    assert.equal(p.texto('temperatura', 20.01, '°C'), '68 °F');
    assert.equal(p.texto('temperatura', -0, '°C', true), '0 °F');
});
