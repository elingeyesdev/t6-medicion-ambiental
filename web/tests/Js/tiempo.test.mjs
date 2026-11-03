import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const catalogo = JSON.parse(readFileSync(new URL('../../../compartido/unidades/presentacion.json', import.meta.url))).grupos;

class Nodo {
    constructor(tipo) {
        this.tagName = tipo.toUpperCase(); this.children = []; this.atributos = {}; this.eventos = new Map();
        this.texto = ''; this.value = ''; this.className = ''; this.open = false; this.hidden = false;
    }
    get textContent() { return this.texto + this.children.map(n => n.textContent).join(''); }
    set textContent(valor) { this.texto = String(valor); this.children = []; }
    append(...nodos) { for (const n of nodos) { n.parentElement = this; this.children.push(n); } }
    replaceChildren(...nodos) { this.texto = ''; this.children = []; this.append(...nodos); }
    add(opcion) { this.append(opcion); if (this.children.length === 1) this.value = opcion.value; }
    setAttribute(clave, valor) { this.atributos[clave] = String(valor); }
    getAttribute(clave) { return this.atributos[clave] ?? null; }
    addEventListener(tipo, fn) { if (!this.eventos.has(tipo)) this.eventos.set(tipo, new Set()); this.eventos.get(tipo).add(fn); }
    removeEventListener(tipo, fn) { this.eventos.get(tipo)?.delete(fn); }
    async emitir(tipo) { await Promise.all([...this.eventos.get(tipo) ?? []].map(fn => fn({type: tipo, target: this}))); }
    querySelectorAll(tipo) {
        return this.children.flatMap(n => [...(n.tagName.toLowerCase() === tipo ? [n] : []), ...n.querySelectorAll(tipo)]);
    }
    querySelector(tipo) { return this.querySelectorAll(tipo)[0] ?? null; }
}

function datosTiempo() {
    const variables = Object.fromEntries([
        ['temperatura', 'Temperatura', '°C'], ['sensacion', 'Sensación', '°C'], ['humedad', 'Humedad', '%'],
        ['probabilidad', 'Probabilidad de lluvia', '%'], ['viento', 'Viento', 'km/h'], ['visibilidad', 'Visibilidad', 'm'],
        ['lluvia', 'Lluvia', 'mm'], ['nieve', 'Nieve', 'cm'], ['nubosidad', 'Nubosidad', '%'],
        ['presion', 'Presión', 'hPa'], ['direccion', 'Dirección', '°'], ['uv', 'UV', ''],
    ].map(([clave, nombre, unidad]) => [clave, {nombre, unidad}]));
    return {origen: 'estimado', zonaHoraria: 'America/La_Paz', variables,
        actual: {instante: '2026-10-31T10:00:00Z', ...Object.fromEntries(Object.keys(variables).map(k => [k, 0]))},
        dias: [{fecha: '2026-10-31', maxima: 20, minima: 0, lluvia: 0}],
        horas: [{instante: '2026-10-31T04:00:00Z', temperatura: 15, viento: 18},
            {instante: '2026-10-31T11:00:00Z', temperatura: 25, viento: 54}],
        historial: [{instante: '2026-10-30T11:00:00Z', temperatura: 0, viento: 0},
            {instante: '2026-10-31T08:00:00Z', temperatura: null, viento: null},
            {instante: '2026-10-31T09:00:00Z', temperatura: 10, viento: 36},
            {instante: '2026-10-31T10:00:00Z', temperatura: 20, viento: 72}]};
}

async function ficha(t, datos = datosTiempo()) {
    const anteriores = Object.fromEntries(['document', 'Option', 'location', 'fetch'].map(k => [k, globalThis[k]]));
    const documento = new Nodo('document');
    documento.createElement = tipo => new Nodo(tipo);
    documento.createElementNS = (_, tipo) => new Nodo(tipo);
    documento.getElementById = id => id === 'configuracion-unidades' ? {textContent: JSON.stringify({catalogo})} : null;
    let consultas = 0;
    Object.assign(globalThis, {document: documento, location: {origin: 'http://localhost'},
        Option: class extends Nodo { constructor(texto, valor) { super('option'); this.textContent = texto; this.value = valor; } },
        fetch: async () => { consultas++; return {ok: true, json: async () => datos}; }});
    const {crearConsultaTiempo} = await import('../../public/js/mapas/tiempo.js');
    const {presentacion} = await import('../../public/js/datos/unidades.js');
    presentacion.establecer({});
    const consulta = crearConsultaTiempo({url: '/mapa/tiempo', latitud: -17.8, longitud: -63.2});
    t.after(() => {
        consulta.cancelar(); presentacion.establecer({});
        for (const [k, v] of Object.entries(anteriores)) { if (v === undefined) delete globalThis[k]; else globalThis[k] = v; }
    });
    await consulta.elemento.querySelector('button').emitir('click');
    const campo = nombre => {
        const etiqueta = consulta.elemento.querySelectorAll('label').find(n => n.textContent.startsWith(nombre));
        assert.ok(etiqueta, `La ficha ofrece un control etiquetado «${nombre}».`);
        return etiqueta.querySelector('select');
    };
    return {elemento: consulta.elemento, documento, presentacion, campo, consultas: () => consultas,
        detalle: () => consulta.elemento.querySelector('details'),
        filas: () => consulta.elemento.querySelector('details').querySelector('tbody').children.map(f => f.children.map(c => c.textContent)),
        grafico: () => consulta.elemento.querySelector('svg'),
        escala: () => consulta.elemento.querySelector('details').querySelectorAll('p').at(-1).textContent};
}

async function elegir(f, nombre, valor) { const campo = f.campo(nombre); campo.value = valor; await campo.emitir('change'); }

test('predetermina pronóstico y el período solo sustituye la serie y la tabla horarias', async t => {
    const f = await ficha(t);
    assert.equal(f.campo('Período').value, 'pronostico');
    assert.deepEqual(f.campo('Período').children.map(n => n.textContent), ['Pronóstico por hora', 'Últimas 24 horas']);
    assert.ok(f.campo('Período').className.includes('form-select'));
    assert.equal(f.detalle().querySelector('summary').textContent, 'Tiempo por hora');
    assert.deepEqual(f.filas().map(f => f[1]), ['15 °C', '25 °C']);
    const actual = f.elemento.querySelector('p').textContent;
    const tarjetas = f.elemento.querySelectorAll('strong').map(n => n.textContent);
    const diario = f.elemento.querySelector('table').textContent;
    const control = f.campo('Período');
    await elegir(f, 'Período', 'historial');
    assert.equal(f.campo('Período'), control, 'Cambiar el período conserva el control que tiene el foco.');
    assert.equal(f.detalle().querySelector('summary').textContent, 'Tiempo por hora');
    assert.match(f.detalle().textContent, /Estimaciones recientes/);
    assert.equal(f.detalle().querySelector('p').hidden, false);
    assert.deepEqual(f.filas().map(f => f[1]), ['0 °C', 'No disponible', '10 °C', '20 °C']);
    assert.equal(f.elemento.querySelectorAll('table').length, 2);
    assert.equal(f.elemento.querySelector('p').textContent, actual);
    assert.deepEqual(f.elemento.querySelectorAll('strong').map(n => n.textContent), tarjetas);
    assert.equal(f.elemento.querySelector('table').textContent, diario);
    await elegir(f, 'Período', 'pronostico');
    assert.equal(f.detalle().querySelector('summary').textContent, 'Tiempo por hora');
    assert.deepEqual(f.filas().map(f => f[1]), ['15 °C', '25 °C']);
    assert.equal(f.detalle().querySelector('p').hidden, true);
    assert.equal(f.consultas(), 1);
});

test('la variable seleccionada usa únicamente los valores y unidades del período elegido', async t => {
    const f = await ficha(t);
    await elegir(f, 'Variable', 'viento');
    assert.deepEqual(f.filas().map(f => f[1]), ['18 km/h', '54 km/h']);
    await elegir(f, 'Período', 'historial');
    assert.equal(f.campo('Variable').value, 'viento');
    assert.deepEqual(f.filas().map(f => f[1]), ['0 km/h', 'No disponible', '36 km/h', '72 km/h']);
    assert.match(f.grafico().getAttribute('aria-label'), /Viento/);
    assert.match(f.escala(), /Mínimo 0 km\/h · Máximo 72 km\/h/);
});

for (const periodo of ['pronostico', 'historial']) test(`${periodo}: los huecos mayores a una hora y los null cortan la curva sin ocultar puntos aislados`, async t => {
    const datos = datosTiempo();
    datos[periodo === 'historial' ? 'historial' : 'horas'] = [
        ['04:00', 0], ['05:00', 10], ['07:00', 20], ['08:00', 30], ['09:00', null], ['10:00', 40],
    ].map(([hora, temperatura]) => ({instante: `2026-10-31T${hora}:00Z`, temperatura}));
    const f = await ficha(t, datos);
    await elegir(f, 'Período', periodo);
    const lineas = f.grafico().querySelectorAll('polyline');
    assert.equal(lineas.length, 2);
    assert.deepEqual(lineas.map(n => n.getAttribute('points').split(' ').length), [2, 2]);
    assert.equal(f.grafico().querySelectorAll('circle').length, 1);
    assert.equal(f.filas().length, 6);
    assert.equal(f.filas()[0][1], '0 °C');
    assert.equal(f.filas()[4][1], 'No disponible');
    assert.doesNotMatch(f.grafico().children.map(n => JSON.stringify(n.atributos)).join(''), /NaN|Infinity/);
});

test('las 24 muestras del corte se muestran desde actual menos 23 horas hasta actual, sin mezclar el pronóstico', async t => {
    const datos = datosTiempo();
    const actual = Date.parse(datos.actual.instante);
    datos.historial = Array.from({length: 24}, (_, i) => ({instante: new Date(actual - (23 - i) * 3600000).toISOString(), temperatura: i}));
    const f = await ficha(t, datos);
    await elegir(f, 'Período', 'historial');
    assert.equal(f.filas().length, 24);
    assert.deepEqual(f.filas()[0], ['30/10/26, 07:00', '0 °C']);
    assert.deepEqual(f.filas().at(-1), ['31/10/26, 06:00', '23 °C']);
    assert.equal(f.grafico().querySelectorAll('polyline').length, 1);
    assert.equal(f.grafico().querySelector('polyline').getAttribute('points').split(' ').length, 24);
    await elegir(f, 'Período', 'pronostico');
    assert.deepEqual(f.filas().map(f => f[1]), ['15 °C', '25 °C']);
});

test('una historia parcial conserva las muestras del intervalo sin añadir horas del pronóstico', async t => {
    const datos = datosTiempo();
    datos.historial = [datos.historial[0], datos.historial.at(-1)];
    const f = await ficha(t, datos);
    await elegir(f, 'Período', 'historial');
    assert.equal(f.filas().length, 2);
    assert.deepEqual(f.filas().map(f => f[1]), ['0 °C', '20 °C']);
    assert.match(f.filas()[0][0], /30.*07:00/);
    assert.match(f.filas()[1][0], /31.*06:00/);
    assert.equal(f.grafico().querySelectorAll('polyline').length, 0);
    assert.equal(f.grafico().querySelectorAll('circle').length, 2);
});

for (const historia of ['ausente', 'vacía']) {
    test(`historia ${historia} ofrece un estado vacío y permite volver al pronóstico`, async t => {
        const datos = datosTiempo();
        if (historia === 'ausente') delete datos.historial;
        else datos.historial = [];
        const f = await ficha(t, datos);
        await elegir(f, 'Período', 'historial');
        assert.deepEqual(f.filas(), []);
        assert.match(f.escala(), /No hay estimaciones recientes disponibles/);
        assert.equal(f.grafico().children.length, 0);
        assert.equal(f.grafico().parentElement.hidden, true);
        assert.equal(f.detalle().querySelector('table').parentElement.hidden, true);
        await elegir(f, 'Período', 'pronostico');
        assert.deepEqual(f.filas().map(f => f[1]), ['15 °C', '25 °C']);
        assert.equal(f.consultas(), 1);
    });
}

test('una variable enteramente ausente muestra sus horas y no inventa ceros ni una curva', async t => {
    const datos = datosTiempo(); datos.historial = datos.historial.map(f => ({...f, temperatura: null}));
    const f = await ficha(t, datos);
    await elegir(f, 'Período', 'historial');
    assert.deepEqual(f.filas().map(f => f[1]), ['No disponible', 'No disponible', 'No disponible', 'No disponible']);
    assert.equal(f.grafico().children.length, 0);
    assert.equal(f.grafico().parentElement.hidden, true);
    assert.equal(f.detalle().querySelector('table').parentElement.hidden, false);
    assert.match(f.escala(), /No hay datos disponibles para esta variable/);
});

test('cambiar unidades conserva período, variable y desplegable abierto sin volver a consultar', async t => {
    const datos = datosTiempo(), copia = structuredClone(datos);
    const f = await ficha(t, datos);
    f.detalle().open = true;
    await elegir(f, 'Período', 'historial');
    await elegir(f, 'Variable', 'viento');
    f.presentacion.establecer({temperatura: 'F', viento: 'm/s'});
    await f.documento.emitir('unidades-cambiadas');
    assert.equal(f.campo('Período').value, 'historial');
    assert.equal(f.campo('Variable').value, 'viento');
    assert.equal(f.detalle().open, true);
    assert.equal(f.detalle().querySelector('summary').textContent, 'Tiempo por hora');
    assert.deepEqual(f.filas().map(f => f[1]), ['0 m/s', 'No disponible', '10 m/s', '20 m/s']);
    assert.match(f.escala(), /Mínimo 0 m\/s · Máximo 20 m\/s/);
    await elegir(f, 'Variable', 'temperatura');
    assert.deepEqual(f.filas().map(f => f[1]), ['32 °F', 'No disponible', '50 °F', '68 °F']);
    assert.equal(f.consultas(), 1);
    assert.deepEqual(datos, copia);
});

test('las horas recientes se formatean en la zona IANA del lugar', async t => {
    const datos = datosTiempo(); datos.zonaHoraria = 'Asia/Kathmandu';
    const f = await ficha(t, datos);
    await elegir(f, 'Período', 'historial');
    assert.match(f.filas()[0][0], /30.*16:45/);
    assert.match(f.filas().at(-1)[0], /31.*15:45/);
});

function datosCambioHorario() {
    const datos = datosTiempo();
    datos.zonaHoraria = 'America/New_York';
    datos.actual.instante = '2026-11-01T07:00:00Z';
    datos.dias[0].fecha = '2026-11-01';
    datos.horas = ['04:00', '05:00', '06:00', '07:00'].map((hora, i) => ({instante: `2026-11-01T${hora}:00Z`, temperatura: i * 10}));
    datos.historial = structuredClone(datos.horas);
    return datos;
}

for (const periodo of ['pronostico', 'historial']) test(`${periodo}: las dos 01:00 del cambio horario distinguen su offset y las otras horas siguen concisas`, async t => {
    const f = await ficha(t, datosCambioHorario());
    await elegir(f, 'Período', periodo);
    const fechas = f.filas().map(f => f[0]);
    assert.deepEqual(fechas, ['1/11/26, 00:00', '1/11/26, 01:00 GMT-4', '1/11/26, 01:00 GMT-5', '1/11/26, 02:00']);
    assert.deepEqual(f.filas().map(f => f[1]), ['0 °C', '10 °C', '20 °C', '30 °C']);
    assert.equal(f.grafico().querySelectorAll('polyline').length, 1, 'Las muestras siguen separadas por una hora real.');
    assert.equal(f.grafico().querySelector('polyline').getAttribute('points').split(' ').length, 4);
    f.presentacion.establecer({temperatura: 'F'});
    await f.documento.emitir('unidades-cambiadas');
    assert.deepEqual(f.filas().map(f => f[0]), fechas);
    assert.equal(f.campo('Período').value, periodo);
    assert.equal(f.consultas(), 1);
});

test('solo las horas ambiguas del período visible llevan offset', async t => {
    const datos = datosCambioHorario(); datos.horas = [datos.horas[1], datos.horas[3]];
    const f = await ficha(t, datos);
    assert.deepEqual(f.filas().map(f => f[0]), ['1/11/26, 01:00', '1/11/26, 02:00']);
    await elegir(f, 'Período', 'historial');
    assert.deepEqual(f.filas().map(f => f[0]), ['1/11/26, 00:00', '1/11/26, 01:00 GMT-4', '1/11/26, 01:00 GMT-5', '1/11/26, 02:00']);
    await elegir(f, 'Período', 'pronostico');
    assert.deepEqual(f.filas().map(f => f[0]), ['1/11/26, 01:00', '1/11/26, 02:00']);
});
