import test from 'node:test';
import assert from 'node:assert/strict';
import { sincronizarCalendario } from '../../public/js/focos-calor/calendario.js';
import { validarPeriodoLocal } from '../../public/js/focos-calor/contrato.js';

function campo(value, limites = {}) {
    const input = { value, getAttribute: n => limites[n] ?? null };
    const fp = input._flatpickr = {
        config: {}, selectedDates: [], altInput: { value: '' }, llamadas: [],
        parseDate: valor => new Date(valor + 'Z'),
        formatDate: fecha => fecha.toISOString().slice(0, 19),
        set(nombre, fecha) { this.config[nombre] = fecha; input.value = ''; },
        setDate(valor, evento) {
            this.llamadas.push(evento);
            const fecha = this.parseDate(valor);
            this.selectedDates = fecha >= (this.config.minDate ?? -Infinity) && fecha <= (this.config.maxDate ?? Infinity) ? [fecha] : [];
            input.value = this.selectedDates.length ? valor : '';
        },
        jumpToDate(fecha, evento) { this.vista = fecha; this.eventoVista = evento; },
        clear(evento) { this.llamadas.push(evento); this.selectedDates = []; this.altInput.value = ''; },
    };
    return input;
}

test('sin componente conserva la entrada nativa y sus límites', () => {
    const input = { value: '2026-11-01T13:20:43' };
    sincronizarCalendario(input);
    assert.equal(input.value, '2026-11-01T13:20:43');
});

test('actualizar límites conserva los segundos del período aplicado sin disparar otra consulta', () => {
    const input = campo('2026-11-01T13:20:43', { min: '2026-10-30T00:00:00', max: '2026-11-02T00:00:00' });
    sincronizarCalendario(input);
    assert.equal(input.value, '2026-11-01T13:20:43');
    assert.equal(input._flatpickr.selectedDates.length, 1);
    assert.deepEqual(input._flatpickr.llamadas, [false]);
    assert.equal(input._flatpickr.vista.toISOString(), '2026-11-01T13:20:43.000Z');
    assert.equal(input._flatpickr.eventoVista, false);
});

test('una fecha fuera del límite permanece visible y se rechaza al consultar en lugar de borrarse', () => {
    const input = campo('2026-11-03T13:20:43', { max: '2026-11-02T00:00:00' });
    sincronizarCalendario(input);
    assert.equal(input.value, '2026-11-03T13:20:43');
    assert.match(input._flatpickr.altInput.value, /2026-11-03/);
    assert.throws(() => validarPeriodoLocal('2026-11-01T00:00:00', input.value, Date.parse('2026-11-02T00:00:00Z')), /futuros/);
});

test('cambiar de período limpia ambos extremos y sus restricciones anteriores', () => {
    for (const n of ['desde', 'hasta']) {
        const input = campo('');
        input._flatpickr.config.minDate = new Date('2026-11-01T00:00:00Z');
        input._flatpickr.altInput.value = 'fecha anterior';
        sincronizarCalendario(input);
        assert.equal(input.value, '');
        assert.equal(input._flatpickr.altInput.value, '');
        assert.equal(input._flatpickr.config.minDate, null);
        assert.equal(input._flatpickr.config.maxDate, undefined);
    }
});
