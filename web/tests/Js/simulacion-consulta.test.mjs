import test from 'node:test';
import assert from 'node:assert/strict';

test('la consola de consulta actualiza promedios sin formulario ni controles de analista', async () => {
    const anterior = globalThis.document;
    const eventos = {};
    const paso = {}, puntos = {}, instante = {}, valor = {}, etiqueta = {};
    const tarjeta = {
        dataset: {promedio: 'temperatura', unidad: '°C'},
        querySelector: selector => selector === '.sim-summary-value' ? valor : etiqueta,
    };
    const estado = {classList: {toggle() {}}};
    const nodos = {'[data-continua-paso]': paso, '[data-continua-puntos]': puntos, '[data-continua-instante]': instante, '.sim-status-dot': estado};
    const pagina = {
        querySelector: selector => nodos[selector] ?? null,
        querySelectorAll: selector => selector === '[data-promedio]' ? [tarjeta] : [],
        addEventListener: (nombre, funcion) => { eventos[nombre] = funcion; },
    };
    globalThis.document = {
        querySelector: () => pagina,
        querySelectorAll: () => [],
        getElementById: () => null,
        addEventListener() {},
    };
    try {
        await import('../../public/js/simulaciones/presentacion.js');
        eventos['simulacion-actualizada']({detail: {estado: 'sin_preparar', puntos: []}});
        assert.equal(puntos.textContent, 0);
        assert.equal(valor.textContent, '—');
        eventos['simulacion-actualizada']({detail: {estado: 'pausada', paso: 3, puntos: [{valores: {temperatura: 10}}, {valores: {temperatura: 20}}]}});
        assert.equal(paso.textContent, 3);
        assert.equal(puntos.textContent, 2);
        assert.equal(valor.textContent, '15 °C');
        assert.equal(etiqueta.textContent, 'Promedio · 2 localidades');
    } finally { globalThis.document = anterior; }
});
