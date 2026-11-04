import test from 'node:test';
import assert from 'node:assert/strict';
import { iniciarAvisos } from '../../public/js/avisos/actualizar.js';

function entorno(consultar) {
    const tareas = [];
    const lista = { dataset: { novedades: '/avisos/novedades' }, innerHTML: 'aviso anterior', contains: () => false };
    const contador = { textContent: '1 sin leer' };
    const estado = { textContent: '' };
    const doc = { hidden: false, activeElement: null, getElementById: id => ({ 'avisos-lista': lista, 'avisos-contador': contador, 'avisos-actualizacion': estado })[id] };
    const detener = iniciarAvisos(doc, consultar, (fn, ms) => tareas.push({ fn, ms }));
    return { doc, lista, contador, estado, tareas, detener, siguiente: async () => { const tarea = tareas.shift(); await tarea.fn(); } };
}
const respuesta = (revision = 'a', sinLeer = 2) => ({ ok: true, status: 200, json: async () => ({ revision, sinLeer, html: '<section>Aviso nuevo</section>', paginacion: '' }) });

test('consulta inmediata con sesión, sin conexiones persistentes ni consultas superpuestas', async () => {
    let resolver;
    let llamadas = 0;
    const e = entorno((url, opciones) => {
        llamadas++;
        assert.equal(url, '/avisos/novedades');
        assert.equal(opciones.credentials, 'same-origin');
        assert.equal(opciones.cache, 'no-store');
        assert.ok(opciones.signal);
        return new Promise(r => { resolver = r; });
    });
    assert.equal(e.tareas[0].ms, 10000);
    const pendiente = e.siguiente();
    assert.equal(e.tareas.length, 0);
    resolver(respuesta());
    await pendiente;
    assert.equal(llamadas, 1);
    assert.equal(e.contador.textContent, '2 sin leer');
    assert.equal(e.lista.innerHTML, '<section>Aviso nuevo</section>');
    assert.equal(e.tareas.length, 1);
    e.detener();
});

test('pestaña oculta no consulta y el foco en una acción conserva la lista', async () => {
    let llamadas = 0;
    const e = entorno(async () => { llamadas++; return respuesta(); });
    e.doc.hidden = true;
    await e.siguiente();
    assert.equal(llamadas, 0);
    e.doc.hidden = false;
    e.lista.contains = () => true;
    await e.siguiente();
    assert.equal(e.lista.innerHTML, 'aviso anterior');
    assert.equal(e.contador.textContent, '2 sin leer');
    e.lista.contains = () => false;
    await e.siguiente();
    assert.equal(e.lista.innerHTML, '<section>Aviso nuevo</section>');
    e.detener();
});

test('error o contrato incompleto conserva los avisos y aumenta la espera', async () => {
    const resultados = [() => { throw new Error('sin red'); }, () => ({ ...respuesta(), json: async () => ({ sinLeer: '2' }) }), () => respuesta()];
    const e = entorno(async () => resultados.shift()());
    await e.siguiente();
    assert.equal(e.lista.innerHTML, 'aviso anterior');
    assert.match(e.estado.textContent, /intentaremos de nuevo/);
    assert.equal(e.tareas[0].ms, 20000);
    await e.siguiente();
    assert.equal(e.lista.innerHTML, 'aviso anterior');
    assert.equal(e.tareas[0].ms, 40000);
    await e.siguiente();
    assert.equal(e.estado.textContent, '');
    assert.equal(e.tareas[0].ms, 10000);
    e.detener();
});

test('sesión vencida o redirigida detiene las consultas y conserva los avisos', async () => {
    for (const r of [{ status: 401 }, { status: 403 }, { status: 419 }, { status: 200, redirected: true }]) {
        const e = entorno(async () => r);
        await e.siguiente();
        assert.equal(e.lista.innerHTML, 'aviso anterior');
        assert.match(e.estado.textContent, /Vuelve a ingresar/);
        assert.equal(e.tareas.length, 0);
    }
});
