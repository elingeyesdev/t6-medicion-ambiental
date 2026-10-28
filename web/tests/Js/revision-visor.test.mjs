import test from 'node:test';
import assert from 'node:assert/strict';
import { agruparProximos, etiquetaDeteccion, nombreCortoMunicipio } from '../../public/js/focos-calor/representacion.js';
import { limitesPeriodo, ajustarCalendarios, periodoReciente } from '../../public/js/focos-calor/periodo.js';
import { validarPeriodoLocal } from '../../public/js/focos-calor/contrato.js';
import { MapaFocos } from '../../public/js/focos-calor/mapa.js';

test('calendarios limitan ambos extremos a 168 horas, con intervalo positivo de segundos', () => {
    const limites = limitesPeriodo('2026-02-27T20:00:00', '2026-03-01T20:00:00');
    assert.deepEqual(limites.desde, { min: '2026-02-22T20:00:00', max: '2026-03-01T19:59:59' });
    assert.deepEqual(limites.hasta, { min: '2026-02-27T20:00:01', max: '2026-03-06T20:00:00' });
    assert.equal(limitesPeriodo('2024-02-28T12:30:00', '').hasta.max, '2024-03-06T12:30:00');
});

test('limpiar y restaurar fechas elimina restricciones antiguas sin inventar otra fecha', () => {
    const ahora = Date.parse('2026-10-27T21:19:30Z');
    const campo = value => ({ value, atributos: {}, setAttribute(k, v) { this.atributos[k] = v; }, removeAttribute(k) { delete this.atributos[k]; } });
    const desde = campo('2026-10-17T00:00'), hasta = campo('2026-10-18T00:00');
    ajustarCalendarios(desde, hasta, ahora);
    assert.equal(hasta.atributos.max, '2026-10-24T00:00:00');
    desde.value = ''; hasta.value = ''; ajustarCalendarios(desde, hasta, ahora);
    assert.deepEqual(desde.atributos, { max: '2026-10-27T17:19:30' }); assert.deepEqual(hasta.atributos, { max: '2026-10-27T17:19:30' });
    desde.value = '2026-10-01T00:00'; ajustarCalendarios(desde, hasta, ahora);
    assert.deepEqual(desde.atributos, { max: '2026-10-27T17:19:30' });
    assert.equal(hasta.atributos.max, '2026-10-08T00:00:00');
    assert.deepEqual(limitesPeriodo('fecha inválida', '', ahora).hasta, { min: '', max: '2026-10-27T17:19:30' });
});

test('el período inicial y los accesos rápidos terminan ahora y respetan siete días al cruzar mes y año', () => {
    for (const momento of ['2026-01-01T02:30:00Z', '2026-03-01T03:59:59Z']) {
        const ahora = Date.parse(momento);
        for (const dias of [1, 7]) {
            const periodo = periodoReciente(dias, ahora);
            assert.equal(Date.parse(periodo.hasta), ahora);
            assert.equal(Date.parse(periodo.hasta) - Date.parse(periodo.desde), dias * 86400000);
        }
    }
    assert.equal(periodoReciente(7, Date.parse('2026-10-27T21:19:30.999Z')).hasta, '2026-10-27T21:19:30.000Z');
});

test('el presente limita ambos calendarios y también la validación manual, sin alterar la selección', () => {
    const ahora = Date.parse('2026-10-28T02:30:00Z');
    const limites = limitesPeriodo('2026-10-27T20:00:00', '2026-10-28T00:00:00', ahora);
    assert.equal(limites.desde.max, '2026-10-27T22:30:00');
    assert.equal(limites.hasta.max, '2026-10-27T22:30:00');
    assert.throws(() => validarPeriodoLocal('2026-10-27T20:00:00', '2026-10-27T22:30:01', ahora), /futuros/);
    assert.deepEqual(validarPeriodoLocal('2026-10-20T22:30:00', '2026-10-27T22:30:00', ahora), { desde: '2026-10-21T02:30:00Z', hasta: '2026-10-28T02:30:00Z' });
});

test('la validación al consultar rechaza edición manual fuera de límites aunque no intervenga el calendario', () => {
    assert.deepEqual(validarPeriodoLocal('2026-10-01T20:00', '2026-10-08T20:00'), { desde: '2026-10-02T00:00:00Z', hasta: '2026-10-09T00:00:00Z' });
    for (const [a, b] of [['2026-10-01T20:00', '2026-10-08T20:00:01'], ['2026-10-01T20:00', '2026-10-01T20:00'], ['2026-10-02T20:00', '2026-10-01T20:00'], ['2026-02-30T20:00', '2026-03-01T20:00']]) {
        assert.throws(() => validarPeriodoLocal(a, b));
    }
});

test('agrupación conserva cada registro una vez y no modifica coordenadas ni objetos', () => {
    const entradas = [0, 10, 20, 100, 100].map((x, i) => Object.freeze({ foco: Object.freeze({ identificador: String(i), geometria: Object.freeze({ coordinates: Object.freeze([x, 1]) }) }), punto: Object.freeze({ x, y: 0 }) }));
    const grupos = agruparProximos(Object.freeze(entradas), 15);
    assert.deepEqual(grupos.map(g => g.length), [3, 2]);
    assert.equal(new Set(grupos.flat()).size, entradas.length);
    assert.deepEqual(grupos.flat(), entradas);
    assert.deepEqual(agruparProximos([], 15), []);
});

test('acercar separa puntos próximos, pero coordenadas idénticas mantienen todos sus registros', () => {
    const puntos = [0, 10, 10].map((x, i) => ({ foco: { identificador: String(i) }, punto: { x, y: 0 } }));
    assert.equal(agruparProximos(puntos, 28).length, 1);
    assert.deepEqual(agruparProximos(puntos.map(e => ({ ...e, punto: { x: e.punto.x * 10, y: 0 } })), 28).map(g => g.length), [1, 2]);
});

const deteccion = (id, longitud, potencia) => ({ identificador: id, geometria: { type: 'Point', coordinates: [longitud, -17.7] }, territorio: { municipio: 'BO070102' }, fechas: { observadoDesde: '2026-10-17T18:30:00Z' }, atributos: { confianza: 'nominal', potenciaRadiativa: potencia } });

test('etiqueta comprensible conserva fecha Bolivia, municipio y confianza, sin datos internos', () => {
    const foco = deteccion('firms-identificador-tecnico', -63, 0);
    const etiqueta = etiquetaDeteccion(foco, 'Cotoca');
    assert.match(etiqueta, /14:30/); assert.match(etiqueta, /Cotoca/); assert.match(etiqueta, /confianza nominal/); assert.doesNotMatch(etiqueta, /FRP|MW/);
    assert.equal(etiqueta.includes(foco.identificador), false);
    assert.match(etiquetaDeteccion({ ...foco, atributos: { ...foco.atributos, potenciaRadiativa: null } }), /Municipio no determinado/);
});

test('cada elección centra su coordenada y sustituye el marco y etiqueta, incluso con ubicaciones repetidas', () => {
    const anteriores = { window: globalThis.window, document: globalThis.document, matchMedia: globalThis.matchMedia };
    const capas = new Set();
    const capa = (tipo, posicion) => ({ tipo, posicion, addTo() { capas.add(this); return this; }, remove() { capas.delete(this); }, setLatLng(p) { this.posicion = p; return this; }, setContent(c) { this.contenido = c.textContent; return this; } });
    globalThis.window = { L: { marker: p => capa('seleccion', p), divIcon: o => o, latLngBounds: p => p, tooltip: () => capa('etiqueta') } };
    globalThis.document = { createElement: () => ({ textContent: '', append(...n) { this.textContent += n.map(e => e.textContent).join(' '); } }), getElementById: () => null };
    globalThis.matchMedia = () => ({ matches: false });
    try {
        const mapa = Object.create(MapaFocos.prototype);
        mapa.datos = [deteccion('A', -63, 0), deteccion('B', -62, 5), deteccion('C', -62, 6)];
        const encuadres = [];
        mapa.mapa = { getZoom: () => 8, fitBounds(p, o) { encuadres.push({ p, o }); } };
        mapa.panelGrupo = { hidden: false };
        mapa.listaGrupo = { querySelectorAll: () => [] };
        mapa.nombreMunicipio = () => 'Cotoca';
        mapa.seleccionar('A');
        assert.equal(mapa.panelGrupo.hidden, true);
        const anterior = mapa.etiquetaActual;
        const marcoAnterior = mapa.seleccionActual;
        assert.deepEqual(anterior.posicion, [-17.7, -63]);
        mapa.seleccionar('B');
        assert.equal(capas.has(anterior), false);
        assert.equal(capas.has(marcoAnterior), false);
        assert.equal([...capas].filter(c => c.tipo === 'etiqueta').length, 1);
        assert.equal([...capas].filter(c => c.tipo === 'seleccion').length, 1);
        assert.deepEqual(mapa.etiquetaActual.posicion, [-17.7, -62]);
        assert.deepEqual(mapa.seleccionActual.posicion, [-17.7, -62]);
        assert.match(mapa.etiquetaActual.contenido, /Cotoca/);
        assert.doesNotMatch(mapa.etiquetaActual.contenido, /FRP/);
        assert.deepEqual(encuadres.map(e => e.p), [[[-17.7, -63], [-17.7, -63]], [[-17.7, -62], [-17.7, -62]]]);
        assert.ok(encuadres.every(e => e.o.maxZoom === 16));
        mapa.seleccionar('C');
        assert.deepEqual(mapa.seleccionActual.posicion, [-17.7, -62]);
        assert.match(mapa.etiquetaActual.contenido, /Detección 3 de 3/);
        // Un zoom manual redibuja la selección, sin volver a encuadrar ni fijar el zoom.
        mapa.actualizarSeleccion();
        assert.equal(encuadres.length, 3);
        mapa.seleccionar(null);
        assert.equal(capas.size, 0);
        assert.equal(mapa.etiquetaActual, null);
        assert.equal(mapa.seleccionActual, null);
    } finally { Object.assign(globalThis, anteriores); }
});

test('la selección deja sitio a los paneles y libera el grupo si la ventana es estrecha, sin alejar un zoom mayor', () => {
    const anteriores = { window: globalThis.window, document: globalThis.document, matchMedia: globalThis.matchMedia };
    const controles = { open: true }, resultados = { hidden: false, offsetWidth: 360 };
    globalThis.window = { L: { latLngBounds: p => p } };
    globalThis.document = { getElementById: id => ({ 'controles-mapa': controles, 'resultados-focos': resultados })[id] };
    globalThis.matchMedia = () => ({ matches: true });
    try {
        const mapa = Object.create(MapaFocos.prototype), encuadres = [];
        let ancho = 1208;
        mapa.panelGrupo = { hidden: false, offsetWidth: 316 };
        mapa.mapa = { getSize: () => ({ x: ancho }), getZoom: () => 18, fitBounds(p, o) { encuadres.push(o); } };
        mapa.centrarDeteccion(deteccion('A', -63, 0));
        assert.equal(controles.open, false);
        assert.deepEqual(encuadres[0].paddingTopLeft, [352, 100]);
        assert.deepEqual(encuadres[0].paddingBottomRight, [392, 80]);
        assert.equal(encuadres[0].maxZoom, 18);
        ancho = 800;
        mapa.centrarDeteccion(deteccion('A', -63, 0));
        assert.equal(mapa.panelGrupo.hidden, true);
        assert.deepEqual(encuadres[1].paddingTopLeft, [32, 100]);
    } finally { Object.assign(globalThis, anteriores); }
});

test('la fila abrevia municipios largos sin modificar la denominación ni la etiqueta completa', () => {
    const nombre = 'Charagua (Autonomía Guaraní Charagua Iyambae)';
    assert.equal(nombreCortoMunicipio(nombre), 'Charagua');
    assert.match(etiquetaDeteccion(deteccion('A', -63, 5), nombre), /Autonomía Guaraní/);
    assert.equal(nombreCortoMunicipio('Santa Cruz de La Sierra'), 'Santa Cruz de La Sierra');
    assert.equal(nombreCortoMunicipio(null), 'Municipio no determinado');
});

test('las llamas conservan coordenadas y los registros coincidentes siguen seleccionables en un grupo', () => {
    const anteriores = { window: globalThis.window, document: globalThis.document, requestAnimationFrame: globalThis.requestAnimationFrame, matchMedia: globalThis.matchMedia };
    const elemento = () => ({ children: [], eventos: {}, dataset: {}, append(...n) { this.children.push(...n); }, replaceChildren() { this.children = []; }, setAttribute(k, v) { this[k] = v; }, addEventListener(k, f) { this.eventos[k] = f; }, querySelectorAll() { return []; }, focus() {} });
    const nodos = new Map(['titulo-grupo', 'controles-mapa'].map(id => [id, elemento()]));
    const creados = [], capas = new Set(), elegidos = [];
    const marker = (posicion, opciones) => {
        const e = elemento(), eventos = {};
        const m = { posicion, opciones, eventos, on(k, f) { eventos[k] = f; return this; }, getElement() { return e; }, addTo() { capas.add(this); eventos.add?.(); return this; } };
        creados.push(m); return m;
    };
    globalThis.document = { createElement: elemento, getElementById: id => nodos.get(id) };
    globalThis.window = { L: { marker, divIcon: o => o, latLngBounds: p => p } };
    globalThis.requestAnimationFrame = () => {};
    globalThis.matchMedia = () => ({ matches: true });
    try {
        const datos = Object.freeze([deteccion('A', -63, 0), deteccion('B', -63, 5), deteccion('C', -60, 2)].map(f => Object.freeze(f)));
        const coordenadas = datos.map(f => [...f.geometria.coordinates]);
        const mapa = Object.create(MapaFocos.prototype);
        Object.assign(mapa, { datos: [], marcadores: new Map(), panelGrupo: elemento(), listaGrupo: elemento(), iniciar: () => true, avisar() {}, alSeleccionar: id => elegidos.push(id), nombreMunicipio: () => 'Cotoca', mapa: { latLngToLayerPoint: p => ({ x: p[1] * 100, y: p[0] * 100 }), fitBounds() {} }, puntos: { clearLayers() { capas.clear(); } } });
        mapa.mostrar(datos, null, null);
        const individuales = creados.filter(m => m.opciones.icon.className === 'marcador-calor');
        assert.deepEqual(individuales.map(m => m.posicion), coordenadas.map(([x, y]) => [y, x]));
        assert.deepEqual(datos.map(f => f.geometria.coordinates), coordenadas);
        const grupo = creados.find(m => m.opciones.icon.className === 'agrupacion-focos');
        assert.equal(capas.size, 2);
        grupo.eventos.click();
        assert.equal(mapa.listaGrupo.children.length, 2);
        mapa.listaGrupo.children[1].eventos.click();
        individuales[2].eventos.click();
        let prevenido = false;
        individuales[2].getElement().eventos.keydown({ key: ' ', preventDefault() { prevenido = true; } });
        assert.deepEqual(elegidos, ['B', 'C', 'C']);
        assert.equal(prevenido, true);
    } finally { Object.assign(globalThis, anteriores); }
});
