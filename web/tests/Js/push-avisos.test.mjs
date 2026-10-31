import test from 'node:test';
import assert from 'node:assert/strict';
import { iniciarPush } from '../../public/js/avisos/push.js';

function crear() {
    const eventos = {};
    const peticiones = [];
    const boton = { disabled: false };
    const consentimiento = { checked: false };
    const estado = { textContent: '' };
    const form = { action: '/avisos/push', dataset: { worker: '/avisos/servicio-push.js' },
        addEventListener: (nombre, fn) => { eventos[nombre] = fn; },
        querySelector: s => s === '[type="submit"]' ? boton : { value: 'csrf-controlado' } };
    const elementos = { 'avisos-push': form, 'push-estado': estado, 'push-consentimiento': consentimiento,
        'push-desactivar': { addEventListener: (nombre, fn) => { eventos.baja = fn; } },
        'avisos-push-publico': { textContent: JSON.stringify({ firebase: { projectId: 'proyecto-controlado' }, vapid: 'vapid-publica' }) } };
    let permisos = 0;
    let cargas = 0;
    const entorno = { isSecureContext: true, Notification: { requestPermission: async () => { permisos++; return 'granted'; } },
        navigator: { serviceWorker: { register: async (url, opciones) => {
            assert.equal(url, form.dataset.worker);
            assert.equal(opciones.scope, '/avisos');
            return 'worker-controlado';
        } } },
        fetch: async (url, opciones) => { peticiones.push({ url, opciones }); return { ok: true, json: async () => ({ mensaje: 'Guardado' }) }; } };
    const cargar = async () => {
        cargas++;
        return [{ initializeApp: c => c }, { getMessaging: c => c, getToken: async (_, c) => {
            assert.equal(c.vapidKey, 'vapid-publica');
            assert.equal(c.serviceWorkerRegistration, 'worker-controlado');
            return 'token-controlado';
        }, onMessage: () => {} }];
    };
    iniciarPush({ getElementById: id => elementos[id] }, entorno, cargar);
    return { consentimiento, estado, peticiones, entorno, boton, permisos: () => permisos, cargas: () => cargas,
        activar: () => eventos.submit({ preventDefault() {} }), baja: () => eventos.baja() };
}

test('abrir la página no pide permiso, carga SDK ni registra dispositivos', async () => {
    const e = crear();
    assert.equal(e.cargas(), 0);
    assert.equal(e.permisos(), 0);
    assert.equal(e.peticiones.length, 0);
    await e.activar();
    assert.match(e.estado.textContent, /Confirma/);
    assert.equal(e.permisos(), 0);
});

test('solo el consentimiento y permiso del navegador permiten registrar el token con CSRF', async () => {
    const e = crear();
    e.consentimiento.checked = true;
    await e.activar();
    assert.equal(e.permisos(), 1);
    assert.equal(e.cargas(), 1);
    assert.equal(e.boton.disabled, false);
    const { url, opciones } = e.peticiones[0];
    assert.equal(url, '/avisos/push');
    assert.equal(opciones.credentials, 'same-origin');
    assert.equal(opciones.headers['X-CSRF-TOKEN'], 'csrf-controlado');
    assert.deepEqual(JSON.parse(opciones.body), { activo: true, token: 'token-controlado', consentimiento: true });
    await e.baja();
    assert.deepEqual(JSON.parse(e.peticiones[1].opciones.body), { activo: false });
});

test('denegación o entorno incompatible no carga SDK ni confirma un alta inexistente', async () => {
    const e = crear();
    e.consentimiento.checked = true;
    e.entorno.isSecureContext = false;
    await e.activar();
    assert.match(e.estado.textContent, /no permite/);
    e.entorno.isSecureContext = true;
    e.entorno.Notification.requestPermission = async () => 'denied';
    await e.activar();
    assert.match(e.estado.textContent, /No se concedió/);
    assert.equal(e.cargas(), 0);
    assert.equal(e.peticiones.length, 0);
});
