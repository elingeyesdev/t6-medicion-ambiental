import test from 'node:test';
import assert from 'node:assert/strict';
import {muestra, muestraVector} from '../../public/js/mapas/raster.js';
import {pasoViento, tramoConViento, crearAnimador, crearParticulasViento} from '../../public/js/mapas/particulas-viento.js';

const cuadro = (cambios = {}) => ({ancho: 2, alto: 2, codificacion: 'viento-uv127',
    rejilla: {oeste: -50, este: 50, sur: -50, norte: 50, filas: 'norte-sur', registro: 'nodos'}, ...cambios});
const pixeles = (r = 137, g = 127, alfa = 255) => Uint8ClampedArray.from(Array.from({length: 4}, () => [r, g, 0, alfa]).flat());
const casi = (a, b) => assert.ok(Math.abs(a - b) < 1e-8, `${a} != ${b}`);

test('componentes georreferenciadas: norte/sur, interpolación y magnitud conservada', () => {
    const p = Uint8ClampedArray.from([127, 254, 0, 255, 254, 127, 0, 255, 127, 0, 0, 255, 0, 127, 0, 255]);
    const norte = muestraVector(cuadro(), p, 50, -50), sur = muestraVector(cuadro(), p, -50, -50);
    assert.equal(norte.v, 360); assert.equal(sur.v, -360);
    assert.deepEqual(muestraVector(cuadro(), p, 0, 0), {u: 0, v: 0, valor: 0});
    casi(muestra(cuadro(), p, 25, -25, true), muestraVector(cuadro(), p, 25, -25).valor);
    const invertido = cuadro({rejilla: {...cuadro().rejilla, filas: 'sur-norte'}});
    assert.equal(muestraVector(invertido, p, -50, -50).v, 360);
});

test('no entrega vectores para escalares, máscaras, rejillas incompletas ni fuera de cobertura', () => {
    assert.equal(muestraVector(cuadro({codificacion: 'temp-era5'}), pixeles(), 0, 0), null);
    assert.equal(muestraVector(cuadro(), pixeles(137, 127, 0), 0, 0), null);
    assert.equal(muestraVector(cuadro(), new Uint8ClampedArray(4), 0, 0), null);
    assert.equal(muestraVector(cuadro(), pixeles(), 60, 0), null);
    const p = pixeles(); p[3] = 0;
    assert.equal(muestraVector(cuadro(), p, 0, 0), null);
    assert.notEqual(muestraVector(cuadro(), p, -50, 50), null);
    for (const codificacion of ['viento-uv', 'viento-direccion']) {
        const c = cuadro({codificacion});
        casi(muestraVector(c, pixeles(), 0, 0).valor, muestra(c, pixeles(), 0, 0, true));
    }
});

test('dirección en pantalla: este/derecha, norte/arriba, límites y calma', () => {
    assert.ok(pasoViento({u: 20, v: 0}, .04).x > 0);
    assert.ok(pasoViento({u: -20, v: 0}, .04).x < 0);
    assert.ok(pasoViento({u: 0, v: 20}, .04).y < 0);
    assert.ok(pasoViento({u: 0, v: -20}, .04).y > 0);
    assert.equal(pasoViento({u: 0, v: 0}, .04), null);
    assert.equal(pasoViento({u: NaN, v: 2}, .04), null);
    assert.equal(pasoViento(null, .04), null);
    assert.equal(pasoViento({u: 20, v: 0}, -1), null);
    assert.ok(Math.hypot(...Object.values(pasoViento({u: 10000, v: 10000}, 3600))) <= 3.000001);
});

test('un trazo se corta al atravesar un hueco, aunque sus extremos tengan datos', () => {
    const campo = x => x > 4 && x < 7 ? null : {u: 20, v: 0};
    assert.equal(tramoConViento(campo, 0, 0, 10, 0), false);
    assert.equal(tramoConViento(campo, 0, 0, 3, 0), true);
    assert.equal(tramoConViento(() => ({u: 0, v: 0}), 0, 0, 1, 1), false);
});

function reloj() {
    let id = 0;
    const pendientes = new Map();
    return {
        solicitar: cb => { pendientes.set(++id, cb); return id; },
        cancelar: n => pendientes.delete(n),
        get cantidad() { return pendientes.size; },
        avanzar(t) { const lote = [...pendientes.values()]; pendientes.clear(); for (const cb of lote) cb(t); },
    };
}

test('el animador limita fotogramas, no duplica bucles y cancela sin saltos de tiempo', () => {
    const r = reloj(), tiempos = [];
    const motor = crearAnimador({...r, dibujar: dt => tiempos.push(dt)});
    motor.iniciar(); motor.iniciar(); assert.equal(r.cantidad, 1);
    r.avanzar(0); r.avanzar(16); r.avanzar(40);
    assert.deepEqual(tiempos, [0, .04]);
    motor.parar(); assert.equal(r.cantidad, 0);
    motor.iniciar(); r.avanzar(1000000); assert.equal(tiempos.at(-1), 0);
    r.avanzar(1001000); assert.equal(tiempos.at(-1), .05);
    motor.parar();
    const interno = crearAnimador({...r, dibujar: () => interno.parar()});
    interno.iniciar(); r.avanzar(1002000); assert.equal(r.cantidad, 0);
});

// Adaptador de Leaflet y canvas para comprobar eventos, dibujo y retirada sin un navegador.
function conMapa(ejecutar, tam = {x: 640, y: 480}) {
    const previos = Object.fromEntries(['document', 'window', 'requestAnimationFrame', 'cancelAnimationFrame'].map(k => [k, globalThis[k]]));
    const r = reloj(), eventos = new Map(), eventosDoc = new Map(), panes = new Map();
    const registrar = (almacen, nombres, cb, quitar = false) => nombres.split(' ').forEach(n => {
        if (!almacen.has(n)) almacen.set(n, new Set());
        almacen.get(n)[quitar ? 'delete' : 'add'](cb);
    });
    const emitir = (almacen, nombre) => [...(almacen.get(nombre) ?? [])].forEach(cb => cb());
    const doc = {hidden: false,
        addEventListener: (n, cb) => registrar(eventosDoc, n, cb),
        removeEventListener: (n, cb) => registrar(eventosDoc, n, cb, true),
        createElement: () => {
            const trazos = [];
            const ctx = {trazos, setTransform() {}, clearRect() { trazos.length = 0; }, beginPath() {},
                moveTo(x, y) { this.inicio = {x, y}; }, lineTo(x, y) { this.fin = {x, y}; },
                stroke() { trazos.push({de: {...this.inicio}, a: {...this.fin}}); }};
            return {style: {}, setAttribute() {}, getContext: () => ctx, remove() { this.retirado = true; }};
        },
    };
    const mapa = {desplazado: false, getSize: () => tam,
        containerPointToLatLng: ([x, y]) => ({lng: x / tam.x * 100 - 50 + (mapa.desplazado ? 200 : 0), lat: 50 - y / tam.y * 100}),
        containerPointToLayerPoint: p => ({x: p[0] + 10, y: p[1] + 20}),
        on: (n, cb) => registrar(eventos, n, cb), off: (n, cb) => registrar(eventos, n, cb, true),
        emitir: n => emitir(eventos, n),
        getPane: n => { if (!panes.has(n)) panes.set(n, {hijos: [], append(c) { this.hijos.push(c); }}); return panes.get(n); },
    };
    const L = {Layer: {extend: metodos => class {
        constructor() { Object.assign(this, metodos); }
        getPane() { return mapa.getPane(this.options.pane); }
        addTo() { this.onAdd(mapa); return this; }
        remove() { this.onRemove(mapa); }
    }}, DomUtil: {setPosition: (c, p) => { c.posicion = p; }}};
    Object.assign(globalThis, {document: doc, window: {devicePixelRatio: 3}, requestAnimationFrame: r.solicitar, cancelAnimationFrame: r.cancelar});
    try { ejecutar({L, mapa, doc, r, panes, visibilidad: () => emitir(eventosDoc, 'visibilitychange'),
        escuchas: () => [...eventos.values(), ...eventosDoc.values()].reduce((n, s) => n + s.size, 0)}); }
    finally { for (const [k, v] of Object.entries(previos)) { if (v === undefined) delete globalThis[k]; else globalThis[k] = v; } }
}

test('dibuja el vector real, pausa al mover/ocultar y libera eventos al retirar', () => conMapa(({L, mapa, doc, r, visibilidad, escuchas}) => {
    const p = pixeles(), copia = p.slice(), c = crearParticulasViento(L, cuadro(), p, 'a', .65).addTo(mapa);
    r.avanzar(0); r.avanzar(40);
    assert.ok(c.ctx.trazos.length > 0);
    assert.ok(c.ctx.trazos.every(t => t.a.x > t.de.x && t.a.y === t.de.y));
    assert.deepEqual(c.canvas.posicion, {x: 10, y: 20});
    mapa.emitir('movestart'); assert.equal(r.cantidad, 0); assert.equal(c.ctx.trazos.length, 0);
    mapa.emitir('moveend'); assert.equal(r.cantidad, 1);
    doc.hidden = true; visibilidad(); assert.equal(r.cantidad, 0);
    doc.hidden = false; visibilidad(); assert.equal(r.cantidad, 1);
    c.setOpacity(.2); assert.equal(c.canvas.style.opacity, '0.2');
    const canvas = c.canvas; c.remove(); assert.equal(r.cantidad, 0); assert.equal(escuchas(), 0); assert.equal(canvas.retirado, true);
    mapa.emitir('moveend'); visibilidad(); assert.equal(r.cantidad, 0);
    assert.deepEqual(p, copia);
}));

test('calma, máscara y viewport fuera del raster no mantienen un bucle vacío', () => conMapa(({L, mapa, r}) => {
    for (const p of [pixeles(127, 127), pixeles(137, 127, 0)]) {
        const c = crearParticulasViento(L, cuadro(), p, 'a', 1).addTo(mapa);
        assert.equal(r.cantidad, 0); c.remove();
    }
    const c = crearParticulasViento(L, cuadro(), pixeles(), 'a', 1).addTo(mapa);
    mapa.desplazado = true; mapa.emitir('moveend'); assert.equal(r.cantidad, 0); c.remove();
}));

test('A/B y cambio de fecha conservan pane y vectores independientes', () => conMapa(({L, mapa, r, panes}) => {
    const a = crearParticulasViento(L, cuadro(), pixeles(137), 'ambiente-a', 1).addTo(mapa);
    const b = crearParticulasViento(L, cuadro(), pixeles(117), 'ambiente-b', 1).addTo(mapa);
    r.avanzar(0); r.avanzar(40);
    assert.ok(a.ctx.trazos.every(t => t.a.x > t.de.x)); assert.ok(b.ctx.trazos.every(t => t.a.x < t.de.x));
    assert.equal(panes.get('ambiente-a').hijos[0], a.canvas); assert.equal(panes.get('ambiente-b').hijos[0], b.canvas);
    a.remove(); assert.equal(r.cantidad, 1);
    const nuevo = crearParticulasViento(L, cuadro(), pixeles(127, 137), 'ambiente-a', .5).addTo(mapa);
    r.avanzar(80); r.avanzar(120);
    assert.ok(nuevo.ctx.trazos.every(t => t.a.y < t.de.y));
    nuevo.remove(); b.remove(); assert.equal(r.cantidad, 0);
}));

test('pantallas grandes limitan el canvas y la cantidad de partículas', () => conMapa(({L, mapa, r}) => {
    const c = crearParticulasViento(L, cuadro(), pixeles(), 'a', 1).addTo(mapa);
    assert.ok(c.canvas.width <= 2048 && c.canvas.height <= 2048);
    r.avanzar(0); r.avanzar(40);
    assert.ok(c.ctx.trazos.length > 0 && c.ctx.trazos.length <= 700); c.remove();
}, {x: 5000, y: 3000}));
