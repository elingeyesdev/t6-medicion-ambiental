import test from 'node:test';
import assert from 'node:assert/strict';
import { MapaSequias } from '../../public/js/sequias/mapa.js';

function entorno() {
    const antes = Object.fromEntries(['window', 'document', 'fetch', 'requestAnimationFrame'].map(k => [k, globalThis[k]]));
    const peticiones = []; const figuras = []; const mapa = { setView() { return this; }, invalidateSize() {}, fitBounds() {} };
    const invalido = { isValid: () => false }, valido = { isValid: () => true };
    globalThis.document = { getElementById: () => null };
    globalThis.requestAnimationFrame = f => f();
    globalThis.window = { L: {
        map: () => mapa,
        layerGroup: () => ({ addTo() { return this; }, clearLayers() { figuras.length = 0; } }),
        featureGroup: () => ({ getBounds: () => invalido }),
        geoJSON: () => ({ addTo() { return this; }, bringToBack() {}, remove() {}, getBounds: () => valido }),
        circleMarker: (p, estilos) => { const n = { addTo() { figuras.push({ p, estilos }); return this; }, on() {}, getElement() { return null; }, setStyle() {} }; return n; },
    } };
    globalThis.fetch = async url => { peticiones.push(url); const id = url.split('/').at(-2); return { ok: true, json: async () => ({ territorio: { identificador: id }, procedencia: { version: 'prueba-v1' }, limite: { type: 'Feature' } }) }; };
    return { peticiones, figuras, restaurar() { for (const [k,v] of Object.entries(antes)) { if (v === undefined) delete globalThis[k]; else globalThis[k] = v; } } };
}

test('el mapa inicial carga el territorio sin pedir índices ni dibujar puntos de sequía', async () => {
    const e = entorno();
    try {
        const mapa = new MapaSequias('/api/v1', 'prueba-v1', () => {}, () => {});
        await mapa.contexto();
        assert.deepEqual(e.peticiones, ['/api/v1/territorios/BO07/limite']);
        assert.equal(e.figuras.length, 0); assert.equal(mapa.porId.size, 0);
        assert.ok(mapa.limite);
    } finally { e.restaurar(); }
});

test('volver al contexto retira los puntos de la consulta anterior y conserva un mapa operativo', async () => {
    const e = entorno();
    try {
        const mapa = new MapaSequias('/api/v1', 'prueba-v1', () => {}, () => {});
        await mapa.mostrar({ datos: [{ idMuestra: 'punto-prueba', coordenadaCelda: [-63, -17], spi: { categoria: 'normal' } }], consulta: { municipio: 'BO070101' } });
        assert.equal(mapa.porId.size, 1); assert.equal(e.figuras.length, 1);
        await mapa.contexto();
        assert.equal(mapa.porId.size, 0); assert.equal(e.figuras.length, 0);
        assert.equal(e.peticiones.at(-1), '/api/v1/territorios/BO07/limite');
    } finally { e.restaurar(); }
});
