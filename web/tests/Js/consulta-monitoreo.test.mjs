import test from 'node:test';
import assert from 'node:assert/strict';
import { consultaMonitoreo, instanteBolivia } from '../../public/js/mapas/consulta-monitoreo.js';

test('Bolivia conserva su día local y convierte la medianoche a UTC sin usar la zona del navegador', () => {
    assert.equal(new Date(instanteBolivia('2026-10-28T00:00')).toISOString(), '2026-10-28T04:00:00.000Z');
});

test('se permite exactamente una semana y no se añade un origen simulado por defecto', () => {
    const consulta = consultaMonitoreo({ desde: '2026-10-21T12:00', hasta: '2026-10-28T12:00', metrica: 'temperatura', pais: '', origen: '' }, Date.parse('2026-10-28T16:00:00Z'));
    assert.equal(consulta.get('desde'), '2026-10-21T16:00:00Z');
    assert.equal(consulta.get('hasta'), '2026-10-28T16:00:00Z');
    assert.equal(consulta.has('origen'), false);
    assert.equal(consulta.has('pais'), false);
});

test('se rechazan futuro, fechas invertidas y una semana más un minuto', () => {
    const ahora = Date.parse('2026-10-28T16:00:00Z');
    for (const [desde, hasta] of [['2026-10-21T11:59', '2026-10-28T12:00'], ['2026-10-28T12:00', '2026-10-28T12:01'], ['2026-10-28T12:00', '2026-10-27T12:00']]) {
        assert.throws(() => consultaMonitoreo({ desde, hasta, metrica: 'aqi' }, ahora), /siete días/);
    }
});

test('se rechazan días inexistentes, formatos ambiguos y horas fuera de rango', () => {
    for (const texto of ['2026-02-29T00:00', '2026-04-31T12:00', '2026-10-28T24:00', '28/10/2026 12:00', '2026-10-28T12:00Z', '']) {
        assert.throws(() => instanteBolivia(texto), /Revisa/);
    }
    assert.equal(new Date(instanteBolivia('2028-02-29T00:00')).toISOString(), '2028-02-29T04:00:00.000Z');
});

test('país y origen se comparten únicamente cuando el usuario los elige', () => {
    const consulta = consultaMonitoreo({ desde: '2026-10-27T12:00', hasta: '2026-10-28T12:00', metrica: 'ica', pais: 'BR', origen: 'simulado' }, Date.parse('2026-10-28T16:00:00Z'));
    assert.equal(consulta.get('pais'), 'BR');
    assert.equal(consulta.get('origen'), 'simulado');
    assert.equal(consulta.get('metrica'), 'ica');
});
