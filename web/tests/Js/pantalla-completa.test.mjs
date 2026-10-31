import test from 'node:test';
import assert from 'node:assert/strict';
import {iniciarPantallaCompleta} from '../../public/js/mapas/pantalla-completa.js';

class Objetivo {
    eventos = new Map(); atributos = new Map(); hidden = false; disabled = false; textContent = '';
    setAttribute(k, v) { this.atributos.set(k, String(v)); }
    getAttribute(k) { return this.atributos.get(k) ?? null; }
    addEventListener(k, f) { if (!this.eventos.has(k)) this.eventos.set(k, new Set()); this.eventos.get(k).add(f); }
    removeEventListener(k, f) { this.eventos.get(k)?.delete(f); }
    emitir(k) { return Promise.all([...this.eventos.get(k) ?? []].map(f => f({type: k, target: this}))); }
    oyentes(k) { return this.eventos.get(k)?.size ?? 0; }
}

function diferida() {
    let resolver, rechazar;
    const promesa = new Promise((res, rej) => { resolver = res; rechazar = rej; });
    return {promesa, resolver, rechazar};
}

function escenario(t, {inicial, sinSolicitud, sinSalida, prohibida} = {}) {
    const documento = new Objetivo(), contenedor = new Objetivo(), boton = new Objetivo(), estado = new Objetivo();
    contenedor.ownerDocument = documento; documento.fullscreenEnabled = !prohibida;
    const ajeno = new Objetivo();
    documento.fullscreenElement = inicial === 'propia' ? contenedor : inicial === 'ajena' ? ajeno : null;
    const llamadas = {entrar: 0, salir: 0, redimensionar: 0};
    const mapa = Object.freeze({centro: [-17.8, -63.2], capas: ['temperatura', 'limites'], seleccion: 'campus', filtros: {variable: 'temperatura'}});
    contenedor.mapa = mapa;
    const entrar = () => {
        llamadas.entrar++;
        return Promise.resolve().then(() => { documento.fullscreenElement = contenedor; return documento.emitir('fullscreenchange'); });
    };
    const salir = () => {
        llamadas.salir++;
        return Promise.resolve().then(() => { documento.fullscreenElement = null; return documento.emitir('fullscreenchange'); });
    };
    if (!sinSolicitud) contenedor.requestFullscreen = entrar;
    if (!sinSalida) documento.exitFullscreen = salir;
    const opciones = {contenedor, boton, estado, redimensionar: () => llamadas.redimensionar++};
    const control = iniciarPantallaCompleta(opciones);
    t.after(() => control.cancelar());
    return {documento, contenedor, boton, estado, ajeno, llamadas, mapa, opciones, control, entrar, salir};
}

test('entra desde el gesto del botón y solo confirma el estado después del navegador; sale sin reemplazar el mapa', async t => {
    const f = escenario(t), antes = structuredClone(f.mapa);
    assert.equal(f.boton.getAttribute('aria-label'), 'Entrar en pantalla completa');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    const entrada = f.boton.emitir('click');
    assert.equal(f.llamadas.entrar, 1, 'La solicitud nativa se realiza sin aplazar el gesto a otra tarea.');
    assert.equal(f.boton.disabled, true);
    assert.equal(f.boton.getAttribute('aria-busy'), 'true');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    await entrada;
    assert.equal(f.documento.fullscreenElement, f.contenedor);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    assert.equal(f.boton.getAttribute('aria-label'), 'Salir de pantalla completa');
    assert.equal(f.boton.getAttribute('title'), 'Salir de pantalla completa');
    assert.equal(f.boton.getAttribute('aria-busy'), 'false');
    assert.equal(f.boton.disabled, false);
    assert.equal(f.llamadas.redimensionar, 1);
    const salida = f.boton.emitir('click');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    await salida;
    assert.equal(f.documento.fullscreenElement, null);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.equal(f.boton.getAttribute('aria-label'), 'Entrar en pantalla completa');
    assert.deepEqual(f.llamadas, {entrar: 1, salir: 1, redimensionar: 2});
    assert.equal(f.contenedor.mapa, f.mapa);
    assert.deepEqual(f.mapa, antes);
});

test('Escape se refleja mediante fullscreenchange sin solicitar otra salida', async t => {
    const f = escenario(t);
    await f.boton.emitir('click');
    f.documento.fullscreenElement = null;
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.equal(f.boton.getAttribute('aria-label'), 'Entrar en pantalla completa');
    assert.deepEqual(f.llamadas, {entrar: 1, salir: 0, redimensionar: 2});
});

for (const [caso, opciones] of [['sin API de entrada', {sinSolicitud: true}], ['sin API de salida', {sinSalida: true}], ['API bloqueada por el navegador', {prohibida: true}]]) {
    test(`${caso}: el control informa su indisponibilidad y no actúa`, async t => {
        const f = escenario(t, opciones);
        assert.equal(f.boton.disabled, true);
        assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
        assert.equal(f.boton.getAttribute('aria-label'), 'Pantalla completa no disponible');
        assert.equal(f.estado.hidden, false);
        assert.match(f.estado.textContent, /no está disponible/);
        await f.boton.emitir('click');
        assert.deepEqual(f.llamadas, {entrar: 0, salir: 0, redimensionar: 0});
    });
}

for (const modo of ['promesa rechazada', 'excepción síncrona']) {
    test(`una entrada denegada con ${modo} no cambia el estado y permite reintentar`, async t => {
        const f = escenario(t);
        f.contenedor.requestFullscreen = () => {
            if (modo === 'excepción síncrona') throw new Error('detalle interno');
            return Promise.reject(new Error('detalle interno'));
        };
        await f.boton.emitir('click');
        assert.equal(f.documento.fullscreenElement, null);
        assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
        assert.equal(f.boton.getAttribute('aria-busy'), 'false');
        assert.equal(f.boton.disabled, false);
        assert.equal(f.estado.hidden, false);
        assert.match(f.estado.textContent, /No se pudo abrir/);
        assert.doesNotMatch(f.estado.textContent, /detalle interno/);
        assert.equal(f.llamadas.redimensionar, 0);
        f.contenedor.requestFullscreen = f.entrar;
        await f.boton.emitir('click');
        assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
        assert.equal(f.estado.hidden, true);
    });
}

test('una salida denegada mantiene el nombre y pressed reales; Escape limpia el aviso', async t => {
    const f = escenario(t);
    await f.boton.emitir('click');
    f.documento.exitFullscreen = () => { f.llamadas.salir++; return Promise.reject(new Error('denegada')); };
    await f.boton.emitir('click');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    assert.equal(f.boton.getAttribute('aria-label'), 'Salir de pantalla completa');
    assert.equal(f.boton.disabled, false);
    assert.match(f.estado.textContent, /No se pudo salir.*Escape/);
    assert.equal(f.llamadas.redimensionar, 1);
    f.documento.fullscreenElement = null;
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.estado.hidden, true);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.equal(f.llamadas.redimensionar, 2);
});

test('no entra sobre una pantalla completa ajena ni la cierra durante la limpieza', async t => {
    const f = escenario(t, {inicial: 'ajena'});
    assert.equal(f.boton.disabled, true);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.match(f.boton.getAttribute('title'), /otra pantalla completa/);
    await f.boton.emitir('click');
    f.control.cancelar();
    assert.equal(f.documento.fullscreenElement, f.ajeno);
    assert.deepEqual(f.llamadas, {entrar: 0, salir: 0, redimensionar: 0});
});

test('al cerrar una pantalla completa ajena se habilita el mapa sin redimensionarlo innecesariamente', async t => {
    const f = escenario(t, {inicial: 'ajena'});
    f.documento.fullscreenElement = null;
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.boton.disabled, false);
    assert.equal(f.llamadas.redimensionar, 0);
    await f.boton.emitir('click');
    assert.equal(f.documento.fullscreenElement, f.contenedor);
    assert.equal(f.llamadas.redimensionar, 1);
});

test('varios clics y fullscreenchange antes de resolver la promesa no duplican solicitudes ni redimensionados', async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => { f.llamadas.entrar++; return pendiente.promesa; };
    const primero = f.boton.emitir('click'), segundo = f.boton.emitir('click'), tercero = f.boton.emitir('click');
    assert.equal(f.llamadas.entrar, 1);
    f.documento.fullscreenElement = f.contenedor;
    await f.documento.emitir('fullscreenchange');
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    assert.equal(f.boton.disabled, true);
    assert.equal(f.llamadas.redimensionar, 1);
    pendiente.resolver();
    await Promise.all([primero, segundo, tercero]);
    assert.equal(f.boton.disabled, false);
    assert.equal(f.llamadas.redimensionar, 1);
});

test('resolver la solicitud sin fullscreenchange no anuncia una entrada inexistente', async t => {
    const f = escenario(t);
    f.contenedor.requestFullscreen = () => Promise.resolve();
    await f.boton.emitir('click');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.equal(f.llamadas.redimensionar, 0);
    f.documento.fullscreenElement = f.contenedor;
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    assert.equal(f.llamadas.redimensionar, 1);
});

test('inicializar dos veces el mismo botón conserva un solo control y una sola solicitud', async t => {
    const f = escenario(t);
    assert.equal(iniciarPantallaCompleta(f.opciones), f.control);
    assert.equal(f.boton.oyentes('click'), 1);
    assert.equal(f.documento.oyentes('fullscreenchange'), 1);
    await f.boton.emitir('click');
    assert.equal(f.llamadas.entrar, 1);
    assert.equal(f.llamadas.redimensionar, 1);
});

test('la limpieza es idempotente, elimina oyentes y cierra únicamente su contenedor', async t => {
    const f = escenario(t, {inicial: 'propia'});
    assert.equal(f.boton.getAttribute('aria-label'), 'Salir de pantalla completa');
    f.control.cancelar(); f.control.cancelar();
    await Promise.resolve();
    assert.equal(f.llamadas.salir, 1);
    assert.equal(f.boton.oyentes('click'), 0);
    assert.equal(f.documento.oyentes('fullscreenchange'), 0);
    assert.equal(f.boton.hidden, true);
    assert.equal(f.estado.hidden, true);
    await f.boton.emitir('click');
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.llamadas.redimensionar, 0);
});

test('una entrada pendiente que termina después de cancelar se cierra sin actualizar la UI retirada', async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => pendiente.promesa;
    const entrada = f.boton.emitir('click');
    f.control.cancelar();
    f.documento.fullscreenElement = f.contenedor;
    pendiente.resolver();
    await entrada;
    assert.equal(f.documento.fullscreenElement, null);
    assert.equal(f.llamadas.salir, 1);
    assert.equal(f.llamadas.redimensionar, 0);
    assert.equal(f.boton.hidden, true);
    assert.equal(f.estado.hidden, true);
});

test('cancelar durante una salida pendiente no solicita una segunda salida', async t => {
    const f = escenario(t, {inicial: 'propia'}), pendiente = diferida();
    f.documento.exitFullscreen = () => { f.llamadas.salir++; return pendiente.promesa; };
    const salida = f.boton.emitir('click');
    f.control.cancelar();
    assert.equal(f.llamadas.salir, 1);
    f.documento.fullscreenElement = null; pendiente.resolver(); await salida;
    assert.equal(f.llamadas.salir, 1);
});

test('una entrada tardía no cierra la pantalla ajena que la reemplazó', async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => pendiente.promesa;
    const entrada = f.boton.emitir('click');
    f.control.cancelar();
    f.documento.fullscreenElement = f.ajeno;
    pendiente.resolver(); await entrada;
    assert.equal(f.documento.fullscreenElement, f.ajeno);
    assert.equal(f.llamadas.salir, 0);
});

test('un rechazo tardío tras cancelar no muestra avisos ni deja una promesa rechazada sin manejar', async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => pendiente.promesa;
    const entrada = f.boton.emitir('click');
    f.control.cancelar(); pendiente.rechazar(new Error('tardío')); await entrada;
    assert.equal(f.estado.hidden, true);
    assert.equal(f.estado.textContent, '');
    assert.equal(f.llamadas.salir, 0);
});

test('un control reiniciado hereda una entrada pendiente sin que el anterior la cierre', async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => { f.llamadas.entrar++; return pendiente.promesa; };
    const entrada = f.boton.emitir('click');
    f.control.cancelar();
    const nuevo = iniciarPantallaCompleta(f.opciones); t.after(() => nuevo.cancelar());
    assert.equal(f.boton.disabled, true);
    await f.boton.emitir('click');
    assert.equal(f.llamadas.entrar, 1);
    f.documento.fullscreenElement = f.contenedor; await f.documento.emitir('fullscreenchange');
    pendiente.resolver(); await entrada;
    assert.equal(f.documento.fullscreenElement, f.contenedor);
    assert.equal(f.llamadas.salir, 0);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    assert.equal(f.boton.disabled, false);
});

test('reiniciar y cancelar el control durante la salida de limpieza no duplica esa salida', async t => {
    const f = escenario(t, {inicial: 'propia'}), pendiente = diferida();
    f.documento.exitFullscreen = () => { f.llamadas.salir++; return pendiente.promesa; };
    f.control.cancelar();
    const nuevo = iniciarPantallaCompleta(f.opciones);
    assert.equal(f.boton.disabled, true);
    await f.boton.emitir('click'); nuevo.cancelar();
    assert.equal(f.llamadas.salir, 1);
    f.documento.fullscreenElement = null; pendiente.resolver();
    await Promise.resolve(); await Promise.resolve();
    assert.equal(f.llamadas.salir, 1);
    assert.equal(f.documento.oyentes('fullscreenchange'), 0);
});
