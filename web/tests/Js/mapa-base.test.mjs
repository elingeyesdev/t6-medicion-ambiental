import test from 'node:test';
import assert from 'node:assert/strict';
import { iniciarMapaBase, leerConfiguracionMapa } from '../../public/js/mapas/base.js';

test('la configuración del mapa rechaza secretos y datos incompletos', () => {
    for (const textContent of ['{', '{}', '{"proveedor":"otro","tokenPublico":"pk.demo"}', '{"proveedor":"mapbox","tokenPublico":"sk.secreto"}']) {
        assert.equal(leerConfiguracionMapa({ textContent }), null);
    }
    assert.deepEqual(leerConfiguracionMapa({ textContent: '{"proveedor":"mapbox","tokenPublico":"pk.demo","baseInicial":"desconocida"}' }), { tokenPublico: 'pk.demo', baseInicial: 'relieve' });
});

test('cambiar de relieve a satélite conserva las capas ambientales y sus registros', () => {
    const prev = { window: globalThis.window, document: globalThis.document, localStorage: globalThis.localStorage };
    const ambiental = { puntos: [-63.2, -17.8], total: 489 }, capas = new Set([ambiental]), solicitudes = [];
    const selector = { disabled: true, eventos: {}, addEventListener(k, f) { this.eventos[k] = f; } };
    const mapa = { removeLayer(c) { capas.delete(c); } }, avisos = [];
    globalThis.document = {
        getElementById: id => id === 'configuracion-mapa' ? { textContent: '{"proveedor":"mapbox","tokenPublico":"pk.demo","baseInicial":"relieve"}' } : selector,
        createElement: () => ({ append() {}, setAttribute() {} }),
    };
    globalThis.localStorage = { getItem: () => null, setItem() {} };
    globalThis.window = { L: {
        tileLayer(url, opciones) { const capa = { url, opciones, eventos: {}, on(k, f) { this.eventos[k] = f; return this; }, addTo() { capas.add(this); return this; } }; solicitudes.push(capa); return capa; },
        control: () => ({ addTo() { this.onAdd(); } }),
    } };
    try {
        const base = iniciarMapaBase(mapa, m => avisos.push(m), 'selector');
        assert.equal(selector.disabled, false);
        assert.ok([...capas].some(c => c.url?.includes('outdoors-v12')));
        selector.value = 'satelite'; selector.eventos.change();
        assert.equal(capas.size, 2);
        assert.ok(capas.has(ambiental));
        assert.deepEqual(ambiental, { puntos: [-63.2, -17.8], total: 489 });
        assert.ok([...capas].some(c => c.url?.includes('satellite-streets-v12')));
        for (const c of solicitudes) { assert.equal(new URL(c.url.replace('{z}', '1').replace('{x}', '1').replace('{y}', '1')).hostname, 'api.mapbox.com'); assert.equal(c.opciones.tileSize, 512); assert.equal(c.opciones.zoomOffset, -1); }
        solicitudes[1].eventos.tileerror(); solicitudes[1].eventos.tileerror();
        assert.equal(avisos.length, 1); assert.ok(capas.has(ambiental));
        base.activar('invalida'); assert.equal(capas.size, 2);
    } finally { Object.assign(globalThis, prev); }
});

test('sin token no se usa otro proveedor ni se tocan los datos ambientales', () => {
    const anterior = globalThis.document, avisos = [];
    globalThis.document = { getElementById: () => null };
    try { assert.equal(iniciarMapaBase({}, m => avisos.push(m), 'selector'), null); assert.equal(avisos.length, 1); }
    finally { globalThis.document = anterior; }
});
