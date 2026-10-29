import test from 'node:test';
import assert from 'node:assert/strict';
import {decodificar, muestra, coordenadaPixel, color, cuadrosDisponibles, crearReproductor} from '../../public/js/mapas/raster.js';
import {crearPresentador} from '../../public/js/datos/unidades.js';
import {readFileSync} from 'node:fs';

const cuadro = (cambios = {}) => ({ancho: 2, alto: 2, codificacion: 'temp-era5',
    rejilla: {oeste: -180, este: 180, sur: -90, norte: 90, filas: 'sur-norte', registro: 'centros'}, ...cambios});
const pixeles = (...rojos) => Uint8ClampedArray.from(rojos.flatMap(r => r === null ? [0, 0, 0, 0] : [r, 0, 0, 255]));
const casi = (a, b, margen = 1e-8) => assert.ok(Math.abs(a - b) < margen, `${a} != ${b}`);

test('distingue las escalas del ETL y de las texturas actuales de temperatura', () => {
    assert.equal(decodificar('temp-era5', 0, 0, 0, 255), -50);
    assert.equal(decodificar('temp-era5', 255, 0, 0, 255), 50);
    assert.equal(decodificar('temp-textura', 0, 0, 0, 255), -60);
    assert.equal(decodificar('temp-textura', 255, 0, 0, 255), 60);
});
test('conserva faltantes transparentes en todas las codificaciones', () => {
    for (const c of ['temp-era5', 'lluvia-escalones', 'viento-uv', 'viento-direccion', 'vis-etl', 'nieve-etl', 'aqi-textura', 'humedad-etl', 'presion-etl']) {
        assert.equal(decodificar(c, 255, 255, 255, 0), null);
        assert.deepEqual(color('temperatura', null), [0, 0, 0, 0]);
    }
});
test('lluvia no lineal respeta escalones y no confunde cero con ausente', () => {
    assert.equal(decodificar('lluvia-escalones', 0, 0, 0, 255), 0);
    assert.equal(decodificar('lluvia-escalones', 255, 0, 0, 255), 150);
    casi(decodificar('lluvia-escalones', 85, 0, 0, 255), 5);
});
test('decodifica componentes U/V y velocidad/dirección sin intercambiar contratos', () => {
    casi(decodificar('viento-uv', 255, 0, 0, 255).valor, Math.hypot(360, -360));
    const direccion = decodificar('viento-direccion', 255, 0, 0, 255);
    casi(direccion.valor, 150); casi(direccion.u, 0); casi(direccion.v, -150);
});
test('interpola U/V antes de obtener magnitud, y no inventa calma en píxeles ausentes', () => {
    const c = cuadro({codificacion: 'viento-uv'});
    const v = Uint8ClampedArray.from([255, 128, 0, 255, 0, 128, 0, 255, 255, 128, 0, 255, 0, 128, 0, 255]);
    casi(muestra(c, v, 0, 0, true), (128 / 255 * 200 - 100) * 3.6);
    v[3] = 0;
    assert.equal(muestra(c, v, 0, 0, true), null);
});
test('usa unidades del ETL para visibilidad, nieve, humedad y presión', () => {
    casi(decodificar('vis-etl', 255, 0, 0, 255), 24.14);
    casi(decodificar('vis-textura', 255, 0, 0, 255), 24.135);
    assert.equal(decodificar('nieve-etl', 255, 0, 0, 255), 150);
    assert.equal(decodificar('nieve-fresca-textura', 0, 255, 0, 255), 300);
    assert.equal(decodificar('humedad-etl', 255, 0, 0, 255), 100);
    assert.equal(decodificar('presion-etl', 255, 0, 0, 255), 1100);
    assert.equal(decodificar('aqi-textura', 100, 0, 0, 255), 200);
});
test('respeta filas de sur a norte y de norte a sur con coordenadas cero', () => {
    assert.equal(muestra(cuadro(), pixeles(0, 255, 85, 170), -45, -90), -50);
    casi(muestra(cuadro(), pixeles(0, 255, 85, 170), 45, -90), -50 + 100 / 3);
    const c = cuadro(); c.rejilla.filas = 'norte-sur';
    assert.equal(muestra(c, pixeles(0, 255, 85, 170), 45, -90), -50);
    casi(muestra(cuadro(), pixeles(0, 255, 85, 170), 0, 0, true), 0);
});
test('no interpola huecos ni ofrece valores fuera del área declarada', () => {
    assert.equal(muestra(cuadro(), pixeles(0, 255, null, 170), 0, 0, true), null);
    const c = cuadro(); c.rejilla = {...c.rejilla, oeste: -85, este: -30, sur: -58, norte: 15};
    assert.equal(muestra(c, pixeles(0, 255, 85, 170), 40, 0), null);
    assert.equal(muestra(c, new Uint8ClampedArray(1), 0, -60), null);
});
test('rejilla 0..360, antimeridiano y nodos conservan ubicación geográfica', () => {
    const c = cuadro(); c.rejilla = {...c.rejilla, oeste: 0, este: 360};
    assert.equal(muestra(c, pixeles(0, 255, 85, 170), -45, -90), 50);
    assert.equal(muestra(c, pixeles(0, 255, 85, 170), -45, 270), 50);
    const n = cuadro(); n.rejilla.registro = 'nodos';
    assert.deepEqual(coordenadaPixel(n, -90, -180), {x: 0, y: 0});
    assert.deepEqual(coordenadaPixel(n, 90, 0), {x: .5, y: 1});
    assert.equal(muestra(cuadro(), pixeles(0, 255, 85, 170), 0, -180, true), muestra(cuadro(), pixeles(0, 255, 85, 170), 0, 180, true));
});
test('leyendas y lectura comparten preferencias sin alterar la textura', () => {
    const catalogo = JSON.parse(readFileSync(new URL('../../../compartido/unidades/presentacion.json', import.meta.url))).grupos;
    const p = crearPresentador({catalogo, preferencias: {temperatura: 'F', viento: 'm/s'}});
    assert.equal(p.texto('temperatura', 0, '°C'), '32 °F');
    assert.equal(p.texto('viento', 36, 'km/h'), '10 m/s');
    assert.equal(decodificar('temp-era5', 0, 0, 0, 255), -50);
});
test('AQI usa vecino cercano y una codificación desconocida falla', () => {
    assert.equal(muestra(cuadro({codificacion: 'aqi-textura'}), pixeles(0, 255, 10, 100), 0, 0, true), 200);
    assert.throws(() => decodificar('inventada', 1, 1, 1, 255));
});
test('no inventa horas intermedias ni modifica las fechas publicadas', () => {
    const c = {cuadros: [{id: 'b', instante: '2026-10-28T18:00:00Z'}, {id: 'a', instante: '2026-10-28T08:00:00Z'}]};
    assert.deepEqual(cuadrosDisponibles(c).map(c => c.id), ['a', 'b']);
    assert.equal(c.cuadros[0].id, 'b');
});
test('reproducción espera la carga y recorre solo los cuadros disponibles', async () => {
    const vistos = [];
    const p = crearReproductor({cantidad: () => 3, intervalo: 1, mostrar: async i => vistos.push(i)});
    await p.iniciar(); assert.deepEqual(vistos, [0, 1, 2]); assert.equal(p.activo, false);
});
test('pausar durante una petición evita avanzar a otra fecha', async () => {
    let resolver; const vistos = [];
    const p = crearReproductor({cantidad: () => 3, intervalo: 1, mostrar: i => { vistos.push(i); return new Promise(r => { resolver = r; }); }});
    const pendiente = p.iniciar(); p.parar(); resolver(); await pendiente;
    assert.deepEqual(vistos, [0]); assert.equal(p.activo, false);
});
test('un error detiene reproducción sin saltar a datos de respaldo', async () => {
    const vistos = [];
    const p = crearReproductor({cantidad: () => 3, intervalo: 1, mostrar: async i => { vistos.push(i); if (i === 1) throw new Error('Ausente'); }});
    await assert.rejects(p.iniciar(), /Ausente/); assert.deepEqual(vistos, [0, 1]); assert.equal(p.activo, false);
});
