import test from 'node:test';
import assert from 'node:assert/strict';
import {categoriaEuropea, cargarAire} from '../../public/js/mapas/aire.js';

test('categorías europeas conservan cero, límites y valores mayores que cien', () => {
    for (const [valor, clase] of [[0, 'buena'], [20, 'buena'], [20.1, 'razonable'], [40, 'razonable'],
        [47, 'moderada'], [60, 'moderada'], [61, 'desfavorable'], [80, 'desfavorable'],
        [81, 'muy-desfavorable'], [100, 'muy-desfavorable'], [101, 'extrema'], [650, 'extrema']]) {
        assert.equal(categoriaEuropea(valor).clase, clase);
    }
    for (const valor of [null, undefined, NaN, Infinity, -1, '30', false]) assert.equal(categoriaEuropea(valor).nombre, 'Sin dato');
});

test('cambiar de lugar cancela la consulta anterior aunque el transporte responda tarde', async () => {
    let terminar, signal;
    const resultados = [], fallos = [], estados = [];
    const carga = cargarAire({consultar: s => { signal = s; return new Promise(r => { terminar = r; }); },
        mostrar: d => resultados.push(d), fallar: () => fallos.push(true), cargando: d => estados.push(d)});
    const pendiente = carga.cargar();
    carga.cancelar(); terminar({lugar: 'anterior'}); await pendiente;
    assert.equal(signal.aborted, true);
    assert.deepEqual(resultados, []); assert.deepEqual(fallos, []); assert.deepEqual(estados, [true, false]);
});

test('una petición atrasada no reemplaza la consulta más nueva ni cambia su estado de carga', async () => {
    const pendientes = [], recibidos = [], estados = [];
    const carga = cargarAire({consultar: () => new Promise(r => pendientes.push(r)), mostrar: d => recibidos.push(d), fallar() {}, cargando: d => estados.push(d)});
    const primera = carga.cargar(), segunda = carga.cargar();
    pendientes[1]('nueva'); await segunda;
    pendientes[0]('vieja'); await primera;
    assert.deepEqual(recibidos, ['nueva']); assert.deepEqual(estados, [true, true, false]);
});

test('el fallo no borra una consulta anterior y permite reintentar sin cargas automáticas', async () => {
    let intento = 0;
    const resultados = [], fallos = [];
    const carga = cargarAire({consultar: async () => { if (++intento === 2) throw new Error('sin red'); return intento; },
        mostrar: d => resultados.push(d), fallar: () => fallos.push(true), cargando() {}});
    assert.equal(intento, 0);
    await carga.cargar(); await carga.cargar(); await carga.cargar();
    assert.deepEqual(resultados, [1, 3]); assert.deepEqual(fallos, [true]);
});
