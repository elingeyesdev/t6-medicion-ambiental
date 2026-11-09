import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { leerConsulta, validarRespuesta, pintarResumen, iniciarRevisionForestal, formatearArea, colorAnio } from '../../public/js/revision/forestal.js';

const id = '78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730';
const consulta = { id, desde: '2024', hasta: '2025' };
const resumen = JSON.parse(await readFile(new URL('../../../docs/forestal147/evidencias/recorte-20261108/resultado/resumen.json', import.meta.url), 'utf8'));
const capa = JSON.parse(await readFile(new URL('../../../docs/forestal147/evidencias/recorte-20261108/resultado/perdida.geojson', import.meta.url), 'utf8'));
const respuesta = () => ({ id, revision: true, resumen: { ...structuredClone(resumen), serie: structuredClone(resumen.serie.filter(f => f.anio >= 2024)) }, capa: structuredClone(capa) });

class Elemento {
    children = []; attrs = {}; listeners = {}; hidden = false; disabled = false; dataset = {}; value = ''; contenido = '';
    style = { setProperty: (k, v) => { this.attrs[k] = v; } };
    constructor(tag = 'div') { this.tagName = tag; }
    set textContent(v) { this.contenido = String(v); this.children = []; }
    get textContent() { return this.contenido + this.children.map(c => c.textContent).join(''); }
    append(...hijos) { this.children.push(...hijos); }
    replaceChildren(...hijos) { this.contenido = ''; this.children = hijos; }
    setAttribute(k, v) { this.attrs[k] = v; }
    addEventListener(tipo, fn) { (this.listeners[tipo] ??= []).push(fn); }
    async emitir(tipo) { await Promise.all((this.listeners[tipo] ?? []).map(fn => fn({ preventDefault() {} }))); }
}
const documento = () => {
    const nodos = new Map();
    const doc = { createElement: tag => new Elemento(tag), getElementById: id => { if (!nodos.has(id)) nodos.set(id, new Elemento()); return nodos.get(id); } };
    doc.getElementById('revision-forestal').dataset.consulta = '/revision/forestal/consulta';
    for (const [k, v] of Object.entries(consulta)) doc.getElementById('rf-' + k).value = v;
    return doc;
};
const http = (datos, status = 200) => ({ ok: status === 200, status, headers: { get: () => 'application/json' }, json: async () => datos });
function leaflet() {
    const estado = { encuadres: [], geometrias: [], retiradas: [] };
    const mapa = { setView() { return this; }, fitBounds(b, opciones) { estado.encuadres.push({ b, opciones }); }, invalidateSize() {}, removeLayer(c) { estado.retiradas.push(c); } };
    const L = { map(el, opciones) { estado.opciones = opciones; return mapa; }, geoJSON(datos, opciones) {
        const grupo = { addTo() { return this; }, getBounds: () => ({ isValid: () => datos.features.length > 0 }), resetStyle(g) { g.reiniciado = true; } };
        for (const f of datos.features) {
            const g = { handlers: {}, getBounds: () => ({ isValid: () => true }), on(tipo, fn) { this.handlers[tipo] = fn; }, setStyle(s) { this.style = s; }, bringToFront() {} };
            g.estiloInicial = opciones.style(f); estado.geometrias.push(g); opciones.onEachFeature(f, g);
        }
        return grupo;
    } };
    return { L, estado };
}
const iniciar = (doc, fetcher, L = null) => iniciarRevisionForestal({ doc, fetcher, L, base: () => {} });

test('código y años estrictos: fechas, arrays, exponentes, saltos e intervalo invertido', () => {
    assert.deepEqual(leerConsulta(id, '2001', '2025'), { id, desde: '2001', hasta: '2025' });
    for (const valor of ['24', '2024-01-01', '2024-01', '2024.0', '2e3', '2024\n', ['2024'], 2024]) assert.throws(() => leerConsulta(id, valor, '2025'));
    for (const valor of [id.toUpperCase(), '../paquete', id + '\n', [], '']) assert.throws(() => leerConsulta(valor, '2024', '2025'));
    assert.throws(() => leerConsulta(id, '2025', '2024'));
});

test('respuesta corresponde a id, revisión y período; no acepta capas o serie de otros años', () => {
    const r = respuesta(); assert.equal(validarRespuesta(r, consulta), r);
    for (const alterar of [r => { r.id = 'x'; }, r => { r.revision = false; }, r => { r.resumen.consulta.hasta = 2023; }, r => { r.resumen.serie[0].anio = 2001; }, r => { r.capa.features[0].properties.anio = 2001; }]) {
        const otro = respuesta(); alterar(otro); assert.throws(() => validarRespuesta(otro, consulta));
    }
});

test('áreas legibles, valores originales intactos, sin inventar cero ante ausencia', () => {
    const r = respuesta(), original = structuredClone(r), doc = documento(); pintarResumen(doc, r);
    assert.deepEqual(r, original);
    assert.equal(formatearArea(1.9148918), new Intl.NumberFormat('es-BO', { maximumFractionDigits: 3 }).format(1.9148918));
    assert.equal(formatearArea(null), 'No informado'); assert.equal(formatearArea(0), '0');
    assert.match(doc.getElementById('rf-resumen').textContent, /Área cartografiada/);
    assert.doesNotMatch(doc.getElementById('rf-resumen').textContent, /1\.9148918/);
    r.resumen.total_periodo_ha = null; r.resumen.conteos_pixeles.perdida_periodo = null;
    pintarResumen(doc, r); assert.match(doc.getElementById('rf-resumen').textContent, /No informado/);
});

test('fuente, límites y base se escriben como texto sin interpretar HTML', () => {
    const doc = documento(), r = respuesta(), ataque = '<img src=x onerror=alert(1)>';
    r.resumen.fuente.version = ataque; r.resumen.limites = [ataque]; pintarResumen(doc, r);
    assert.match(doc.getElementById('rf-contexto').textContent, /<img/);
    assert.equal(doc.getElementById('rf-limites').children[0].textContent, ataque);
    assert.equal(doc.getElementById('rf-limites').children[0].children.length, 0);
    assert.match(doc.getElementById('rf-fuente').textContent, /Hansen GFC/);
});

test('25 colores estables y leyenda con años escritos', () => {
    assert.equal(new Set(Array.from({ length: 25 }, (_, n) => colorAnio(2001 + n))).size, 25);
    assert.equal(colorAnio(2024), colorAnio(2024));
    const doc = documento(); pintarResumen(doc, respuesta());
    assert.equal(doc.getElementById('rf-leyenda').children.length, 2);
    assert.match(doc.getElementById('rf-leyenda').textContent, /20242025/);
});

test('GET privado con credenciales, sin caché y loading exacto; no consulta al inicializar', async () => {
    const doc = documento(); let llamada, resolver;
    const ui = iniciar(doc, (url, opciones) => { llamada = { url, opciones }; return new Promise(r => { resolver = r; }); });
    assert.equal(llamada, undefined);
    const trabajo = ui.consultar(); assert.equal(doc.getElementById('rf-estado').textContent, 'Cargando…');
    assert.match(llamada.url, /^\/revision\/forestal\/consulta\?id=/); assert.equal(llamada.opciones.credentials, 'same-origin');
    assert.equal(llamada.opciones.cache, 'no-store'); assert.equal(llamada.opciones.headers.Accept, 'application/json');
    resolver(http(respuesta())); await trabajo;
    assert.equal(doc.getElementById('rf-resultados').hidden, false);
    assert.equal(doc.getElementById('rf-consultar').disabled, false);
});

test('Leaflet usa rueda, selección desde lista y mapa, centrado y áreas redondeadas', async () => {
    const doc = documento(), { L, estado } = leaflet();
    await iniciar(doc, async () => http(respuesta()), L).consultar();
    assert.equal(estado.opciones.scrollWheelZoom, true);
    const boton = doc.getElementById('rf-poligonos').children[0].children[0];
    assert.doesNotMatch(boton.textContent, /0\.07365255/);
    await boton.emitir('click'); assert.equal(boton.attrs['aria-pressed'], 'true');
    assert.match(doc.getElementById('rf-seleccion').textContent, /año 2024/);
    estado.geometrias[1].handlers.click(); assert.equal(boton.attrs['aria-pressed'], 'false');
    assert.equal(estado.geometrias[0].reiniciado, true);
    assert.ok(estado.encuadres.length >= 3); assert.equal(estado.encuadres.at(-1).opciones.animate, false);
});

test('capa vacía conserva cero informado y centra el bbox real tras una selección', async () => {
    const doc = documento(), { L, estado } = leaflet(); let datos = respuesta();
    const ui = iniciar(doc, async () => http(datos), L); await ui.consultar();
    await doc.getElementById('rf-poligonos').children[0].children[0].emitir('click');
    datos = respuesta(); datos.capa.features = []; datos.resumen.total_periodo_ha = 0; datos.resumen.conteos_pixeles.perdida_periodo = 0;
    await ui.consultar();
    assert.match(doc.getElementById('rf-estado').textContent, /No hay polígonos/);
    assert.equal(doc.getElementById('rf-centrar').disabled, true);
    assert.deepEqual(estado.encuadres.at(-1).b, [[resumen.bbox[1], resumen.bbox[0]], [resumen.bbox[3], resumen.bbox[2]]]);
    assert.match(doc.getElementById('rf-resumen').textContent, /Área cartografiada \(ha\)0/);
});

test('editar filtros o recibir error retira la capa y cifras de la consulta anterior', async () => {
    const doc = documento(), { L, estado } = leaflet(); let datos = http(respuesta());
    const ui = iniciar(doc, async () => datos, L); await ui.consultar();
    await doc.getElementById('rf-formulario').emitir('input');
    assert.equal(doc.getElementById('rf-resultados').hidden, true); assert.equal(doc.getElementById('rf-resumen').children.length, 0);
    assert.equal(estado.retiradas.length, 1);
    datos = http({ error: { mensaje: '<b>Paquete ausente</b>' } }, 404); await ui.consultar();
    assert.equal(doc.getElementById('rf-estado').textContent, '<b>Paquete ausente</b>');
    assert.equal(doc.getElementById('rf-estado').children.length, 0);
    assert.equal(doc.getElementById('rf-resultados').hidden, true);
});

test('no acepta HTML de login ni deja datos visibles al perder sesión', async () => {
    const doc = documento();
    await iniciar(doc, async () => ({ ok: true, headers: { get: () => 'text/html' } })).consultar();
    assert.equal(doc.getElementById('rf-resultados').hidden, true);
    assert.match(doc.getElementById('rf-estado').textContent, /sesión/);
});

test('respuesta anterior tardía no reemplaza la consulta más reciente', async () => {
    const doc = documento(); let resolver, n = 0;
    const ui = iniciar(doc, async () => ++n === 1 ? new Promise(r => { resolver = r; }) : http(respuesta()));
    const primera = ui.consultar(); await ui.consultar();
    resolver(http({ error: { mensaje: 'Respuesta vieja' } }, 422)); await primera;
    assert.match(doc.getElementById('rf-estado').textContent, /Consulta completada/);
    assert.equal(doc.getElementById('rf-resultados').hidden, false);
});

test('markup reutiliza layout/Leaflet y detalles; JS no usa sumideros HTML', async () => {
    const blade = await readFile(new URL('../../resources/views/revision/forestal.blade.php', import.meta.url), 'utf8');
    const js = await readFile(new URL('../../public/js/revision/forestal.js', import.meta.url), 'utf8');
    assert.match(blade, /@extends\('layouts.app'\)/); assert.match(blade, /<details><summary>Fuente y límites/);
    assert.match(blade, /Revisión técnica\. Método pendiente de aprobación\./);
    assert.doesNotMatch(js, /innerHTML|outerHTML|insertAdjacentHTML|eval\(/);
    assert.doesNotMatch(blade, /Acceso privado ·|Leyenda ·|Id SHA256/);
});
