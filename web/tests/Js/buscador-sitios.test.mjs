import test from 'node:test';
import assert from 'node:assert/strict';
import {iniciarBuscadorLugar} from '../../public/js/mapas/buscador.js';

class Nodo {
    constructor() { this.oyentes = new Map(); this.children = []; this.value = ''; this.textContent = ''; this.disabled = false; }
    addEventListener(tipo, fn) { this.oyentes.set(tipo, [...(this.oyentes.get(tipo) ?? []), fn]); }
    async emitir(tipo, evento = {}) { for (const fn of this.oyentes.get(tipo) ?? []) await fn({preventDefault() {}, ...evento}); }
    dispatchEvent(evento) { void this.emitir(evento.type, evento); return true; }
    append(...nodos) { this.children.push(...nodos); }
    replaceChildren() { this.children = []; }
}

function interfaz(t, respuesta, centro = () => ({lat: 0, lng: 360})) {
    const anterior = {document: globalThis.document, fetch: globalThis.fetch, location: globalThis.location};
    const entrada = new Nodo(), tipo = new Nodo(), buscar = new Nodo(), formulario = new Nodo(), resultados = new Nodo(), estado = new Nodo();
    tipo.value = 'lugar'; entrada.value = 'Parque de prueba';
    formulario.elements = {texto: entrada, tipo}; formulario.querySelector = () => buscar;
    formulario.reset = () => { entrada.value = ''; tipo.value = 'lugar'; void formulario.emitir('reset'); };
    const nodos = {'buscar-lugar': formulario, 'lugares-encontrados': resultados, 'estado-buscador': estado};
    globalThis.document = {getElementById: id => nodos[id], createElement: () => new Nodo()};
    globalThis.location = {origin: 'http://localhost', href: 'http://localhost/mapa'};
    const peticiones = [], seleccion = [];
    globalThis.fetch = async (url, opciones) => { peticiones.push({url: new URL(url), opciones}); return respuesta(url, opciones); };
    t.after(() => { for (const [k,v] of Object.entries(anterior)) if (v === undefined) delete globalThis[k]; else globalThis[k] = v; });
    iniciarBuscadorLugar('/mapa/buscar', lugar => seleccion.push(lugar), centro);
    return {entrada, tipo, buscar, formulario, resultados, estado, peticiones, seleccion};
}

const lugar = {nombre: 'Parque de prueba', descripcion: 'Ciudad de prueba', latitud: 0, longitud: 0, limites: null};
const correcta = () => ({ok: true, json: async () => ({fuente: 'Mapbox', datos: [lugar]})});

test('buscar un sitio usa el centro elegido, preserva cero y solo selecciona al pulsar el resultado', async t => {
    const i = interfaz(t, correcta);
    assert.equal(i.peticiones.length, 0);
    let seleccionNotificada = false;
    i.formulario.addEventListener('lugar-seleccionado', () => { seleccionNotificada = true; });
    i.tipo.value = 'sitio';
    await i.formulario.emitir('submit');
    const q = i.peticiones[0].url.searchParams;
    assert.equal(q.get('tipo'), 'sitio'); assert.equal(q.get('latitud'), '0'); assert.equal(q.get('longitud'), '0');
    assert.equal(i.resultados.children.length, 1); assert.equal(i.seleccion.length, 0);
    await i.resultados.children[0].children[0].emitir('click');
    assert.equal(seleccionNotificada, true);
    assert.deepEqual(i.seleccion, [lugar]); assert.equal(i.resultados.children.length, 0);
    assert.equal(globalThis.location.href, 'http://localhost/mapa');
});

test('la búsqueda actual conserva su petición sin añadir posición o tipo', async t => {
    const i = interfaz(t, correcta);
    await i.formulario.emitir('submit');
    assert.deepEqual([...i.peticiones[0].url.searchParams], [['texto', 'Parque de prueba']]);
    assert.equal(i.buscar.disabled, false);
});

test('cambiar de tipo cancela la consulta y una respuesta anterior no repuebla resultados', async t => {
    let responder;
    const i = interfaz(t, () => new Promise(resolve => { responder = resolve; }));
    i.tipo.value = 'sitio';
    const pendiente = i.formulario.emitir('submit');
    i.tipo.value = 'lugar'; await i.tipo.emitir('change');
    assert.equal(i.peticiones[0].opciones.signal.aborted, true);
    responder(correcta()); await pendiente;
    assert.equal(i.resultados.children.length, 0); assert.equal(i.estado.textContent, ''); assert.equal(i.buscar.disabled, false);
});

test('un fallo conserva el mensaje útil sin mostrar opciones anteriores', async t => {
    const i = interfaz(t, () => ({ok: false, json: async () => ({error: {mensaje: 'No se pudo buscar. Inténtalo de nuevo.'}})}));
    i.tipo.value = 'sitio'; await i.formulario.emitir('submit');
    assert.equal(i.estado.textContent, 'No se pudo buscar. Inténtalo de nuevo.');
    assert.equal(i.resultados.children.length, 0); assert.equal(i.buscar.disabled, false);
});
