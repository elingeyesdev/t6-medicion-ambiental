import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
import {iniciarPantallaCompleta} from '../../public/js/mapas/pantalla-completa.js';

class Objetivo {
    eventos = new Map(); atributos = new Map(); hidden = false; disabled = false; textContent = ''; isConnected = true;
    children = []; parentElement = null; clases = new Set(); estilos = new Map();
    classList = {
        contains: k => this.clases.has(k), add: k => this.clases.add(k), remove: k => this.clases.delete(k),
        toggle: (k, activa = !this.clases.has(k)) => activa ? this.clases.add(k) : this.clases.delete(k),
    };
    style = {
        getPropertyValue: k => this.estilos.get(k)?.valor ?? '', getPropertyPriority: k => this.estilos.get(k)?.prioridad ?? '',
        setProperty: (k, valor, prioridad = '') => this.estilos.set(k, {valor, prioridad}),
        removeProperty: k => this.estilos.delete(k),
    };
    setAttribute(k, v) { this.atributos.set(k, String(v)); }
    getAttribute(k) { return this.atributos.get(k) ?? null; }
    hasAttribute(k) { return this.atributos.has(k); }
    removeAttribute(k) { this.atributos.delete(k); }
    get inert() { return this.hasAttribute('inert'); }
    set inert(valor) { if (valor) this.setAttribute('inert', ''); else this.removeAttribute('inert'); }
    append(...nodos) { for (const n of nodos) { this.children.push(n); n.parentElement = this; n.ownerDocument = this.ownerDocument; } }
    contains(n) { return this === n || this.children.some(hijo => hijo.contains(n)); }
    closest(selector) { for (let n = this; n; n = n.parentElement) if (selector === '[inert]' && n.hasAttribute('inert')) return n; return null; }
    focus(opciones) { if (!this.isConnected || this.disabled || this.closest('[inert]')) return; this.ownerDocument.activeElement = this; this.foco = opciones; }
    addEventListener(k, f) { if (!this.eventos.has(k)) this.eventos.set(k, new Set()); this.eventos.get(k).add(f); }
    removeEventListener(k, f) { this.eventos.get(k)?.delete(f); }
    emitir(k, datos = {}) {
        const evento = {type: k, target: this, defaultPrevented: false, detenida: false,
            preventDefault() { this.defaultPrevented = true; }, stopPropagation() { this.detenida = true; }, ...datos};
        this.ultimoEvento = evento;
        const resultados = [];
        for (const listener of this.eventos.get(k) ?? []) {
            resultados.push(listener(evento));
            // En estos escenarios se emite desde un descendiente: captura del
            // mapa antes del manejador de navegación en la fase de burbujeo.
            if (evento.detenida) break;
        }
        return Promise.all(resultados);
    }
    dispatchEvent(evento) {
        for (const listener of this.eventos.get(evento.type) ?? []) listener(evento);
        return !evento.defaultPrevented;
    }
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
    documento.documentElement = new Objetivo(); documento.documentElement.ownerDocument = documento;
    documento.body = new Objetivo(); documento.documentElement.append(documento.body);
    const saltar = new Objetivo(), layout = new Objetivo(), lateral = new Objetivo(), principal = new Objetivo();
    const cabecera = new Objetivo(), contenido = new Objetivo(), pie = new Objetivo(), otro = new Objetivo(), enlace = new Objetivo();
    documento.body.append(saltar, layout); layout.append(lateral, principal);
    principal.append(cabecera, contenido, pie); cabecera.append(enlace); contenido.append(contenedor, otro);
    contenedor.append(boton, estado); documento.activeElement = boton;
    contenedor.setAttribute('role', 'region'); contenedor.setAttribute('aria-label', 'Mapa ambiental y controles');
    estado.hidden = true;
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
    return {documento, contenedor, boton, estado, ajeno, llamadas, mapa, opciones, control, entrar, salir,
        saltar, lateral, cabecera, pie, otro, enlace};
}

function navegacion(f, {movil = false} = {}) {
    const documento = f.documento;
    documento.documentElement.dataset = {bsTheme: 'light'};
    const abrir = new Objetivo(), contraer = new Objetivo(), fondo = new Objetivo(), tema = new Objetivo(), enlace = new Objetivo();
    f.cabecera.append(abrir, tema); f.lateral.append(contraer, enlace);
    documento.body.children[1].append(fondo);
    f.lateral.querySelector = () => enlace;
    f.lateral.querySelectorAll = () => [enlace];
    const elementos = new Map([['menu-panel', f.lateral], ['abrir-menu', abrir], ['contraer-menu', contraer], ['fondo-menu', fondo], ['cambiar-tema', tema]]);
    documento.getElementById = id => elementos.get(id);
    const cambios = [];
    const media = {matches: movil, addEventListener: (tipo, listener) => { if (tipo === 'change') cambios.push(listener); }};
    runInNewContext(readFileSync(new URL('../../public/js/panel/navegacion.js', import.meta.url), 'utf8'), {
        document: documento, matchMedia: () => media, localStorage: {setItem() {}},
    });
    return {abrir, enlace, fondo, cambiarAncho(esMovil) {
        media.matches = esMovil;
        for (const listener of cambios) listener();
    }};
}

async function restaurar(f, salida) {
    if (salida === 'botón') await f.boton.emitir('click');
    else await f.documento.emitir('keydown', {key: 'Escape', target: f.boton});
}

for (const salida of ['botón', 'Escape']) {
    test(`navegación real: escritorio a móvil conserva el menú cerrado aislado al restaurar por ${salida}`, async t => {
        const f = escenario(t, {prohibida: true}), nav = navegacion(f);
        nav.enlace.focus();
        await f.boton.emitir('click');
        assert.equal(f.lateral.inert, true);
        nav.cambiarAncho(true);
        assert.equal(f.lateral.inert, true);
        await restaurar(f, salida);
        assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
        assert.equal(f.documento.documentElement.classList.contains('menu-abierto'), false);
        assert.equal(f.lateral.inert, true, 'El menú móvil oculto no entra al recorrido Tab.');
        assert.equal(f.documento.activeElement, f.boton, 'No se devuelve foco al enlace del menú que ahora está oculto.');
        assert.equal(f.cabecera.inert, false);
        assert.equal(f.documento.body.style.getPropertyValue('overflow-y'), '');
    });

    test(`navegación real: móvil a escritorio mantiene aislamiento activo y lo retira al restaurar por ${salida}`, async t => {
        const f = escenario(t, {prohibida: true}), nav = navegacion(f, {movil: true});
        f.enlace.focus();
        await f.boton.emitir('click');
        nav.cambiarAncho(false);
        assert.equal(f.lateral.inert, true, 'El menú de escritorio sigue aislado mientras el mapa está ampliado.');
        await restaurar(f, salida);
        assert.equal(f.lateral.inert, false, 'Al restaurar se aplica el ancho actual, no el inert de la entrada.');
        assert.equal(f.documento.activeElement, f.enlace);
        assert.equal(f.documento.documentElement.style.getPropertyValue('overflow-y'), '');
    });

    test(`navegación real: menú móvil abierto recupera accesibilidad antes del foco al restaurar por ${salida}`, async t => {
        const f = escenario(t, {prohibida: true}), nav = navegacion(f, {movil: true});
        await nav.abrir.emitir('click');
        assert.equal(f.documento.activeElement, nav.enlace);
        assert.equal(f.lateral.inert, false);
        await f.boton.emitir('click');
        assert.equal(f.lateral.inert, true);
        assert.equal(f.documento.documentElement.classList.contains('menu-abierto'), true);
        await restaurar(f, salida);
        assert.equal(f.documento.documentElement.classList.contains('menu-abierto'), true);
        assert.equal(f.lateral.inert, false);
        assert.equal(f.documento.activeElement, nav.enlace, 'Nav recalcula inert antes de devolver foco al menú abierto.');
        assert.equal(nav.fondo.hidden, false);
    });
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

test('una salida nativa externa se refleja sin solicitar otra salida', async t => {
    const f = escenario(t);
    await f.boton.emitir('click');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    f.documento.fullscreenElement = null;
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.equal(f.boton.getAttribute('aria-label'), 'Entrar en pantalla completa');
    assert.deepEqual(f.llamadas, {entrar: 1, salir: 0, redimensionar: 2});
});


test('Escape nativo sale antes del cierre de paneles y conserva el mismo mapa', async t => {
    const f = escenario(t), antes = structuredClone(f.mapa);
    let cierresDePanel = 0;
    f.documento.addEventListener('keydown', () => cierresDePanel++);
    await f.boton.emitir('click');
    const salida = f.documento.emitir('keydown', {key: 'Escape', target: f.boton});
    const evento = f.documento.ultimoEvento;
    await salida;
    assert.equal(evento.defaultPrevented, true);
    assert.equal(evento.detenida, true);
    assert.equal(cierresDePanel, 0);
    assert.equal(f.documento.fullscreenElement, null);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.equal(f.boton.getAttribute('aria-label'), 'Entrar en pantalla completa');
    assert.equal(f.llamadas.salir, 1);
    assert.equal(f.contenedor.mapa, f.mapa);
    assert.deepEqual(f.mapa, antes);
});

test('Escape fuera del mapa nativo conserva los atajos de paneles y no cierra otra pantalla completa', async t => {
    for (const inicial of [undefined, 'ajena']) {
        const f = escenario(t, {inicial});
        let cierresDePanel = 0;
        f.documento.addEventListener('keydown', () => cierresDePanel++);
        await f.documento.emitir('keydown', {key: 'Escape'});
        assert.equal(f.documento.ultimoEvento.defaultPrevented, false);
        assert.equal(cierresDePanel, 1);
        assert.equal(f.llamadas.salir, 0);
        assert.equal(f.documento.fullscreenElement, inicial === 'ajena' ? f.ajeno : null);
    }
});

test('varios Escape durante una salida nativa pendiente no duplican solicitudes', async t => {
    const f = escenario(t), pendiente = diferida();
    await f.boton.emitir('click');
    f.documento.exitFullscreen = () => {
        f.llamadas.salir++;
        return pendiente.promesa.then(() => { f.documento.fullscreenElement = null; return f.documento.emitir('fullscreenchange'); });
    };
    const primera = f.documento.emitir('keydown', {key: 'Escape'});
    assert.equal(f.llamadas.salir, 1);
    assert.equal(f.boton.getAttribute('aria-busy'), 'true');
    assert.equal(f.boton.disabled, true);
    await f.documento.emitir('keydown', {key: 'Escape'});
    assert.equal(f.llamadas.salir, 1);
    pendiente.resolver();
    await primera;
    assert.equal(f.boton.getAttribute('aria-busy'), 'false');
    assert.equal(f.boton.disabled, false);
    assert.equal(f.documento.fullscreenElement, null);
});

test('un rechazo de Escape nativo conserva el estado real y permite reintentar con el botón', async t => {
    const f = escenario(t);
    await f.boton.emitir('click');
    f.documento.exitFullscreen = () => { f.llamadas.salir++; return Promise.reject(new Error('detalle privado')); };
    await f.documento.emitir('keydown', {key: 'Escape'});
    assert.equal(f.documento.fullscreenElement, f.contenedor);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
    assert.equal(f.boton.disabled, false);
    assert.match(f.estado.textContent, /No se pudo salir.*botón/);
    assert.doesNotMatch(f.estado.textContent, /detalle privado/);
    f.documento.exitFullscreen = f.salir;
    await f.boton.emitir('click');
    assert.equal(f.documento.fullscreenElement, null);
    assert.equal(f.estado.hidden, true);
});

for (const [caso, opciones] of [['sin API de entrada', {sinSolicitud: true}], ['sin API de salida', {sinSalida: true}], ['API bloqueada por el navegador', {prohibida: true}]]) {
    test(`${caso}: amplía dentro de la página y restaura sin solicitar pantalla completa nativa`, async t => {
        const f = escenario(t, opciones);
        assert.equal(f.boton.disabled, false);
        assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
        assert.equal(f.boton.getAttribute('aria-label'), 'Ampliar mapa');
        assert.equal(f.estado.hidden, true);
        await f.boton.emitir('click');
        assert.equal(f.documento.fullscreenElement, null);
        assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), true);
        assert.equal(f.contenedor.getAttribute('aria-label'), 'Mapa ampliado en esta página');
        assert.equal(f.boton.getAttribute('aria-label'), 'Restaurar mapa');
        assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
        assert.equal(f.estado.hidden, false);
        assert.equal(f.estado.textContent, 'Mapa ampliado en esta página');
        assert.deepEqual(f.llamadas, {entrar: 0, salir: 0, redimensionar: 1});
        await f.boton.emitir('click');
        assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
        assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
        assert.equal(f.estado.hidden, true);
        assert.deepEqual(f.llamadas, {entrar: 0, salir: 0, redimensionar: 2});
    });
}

for (const modo of ['promesa rechazada', 'excepción síncrona']) {
    test(`una entrada nativa denegada con ${modo} activa el respaldo y permite restaurar y reintentar`, async t => {
        const f = escenario(t);
        f.contenedor.requestFullscreen = () => {
            f.llamadas.entrar++;
            if (modo === 'excepción síncrona') throw new Error('detalle interno');
            return Promise.reject(new Error('detalle interno'));
        };
        await f.boton.emitir('click');
        assert.equal(f.documento.fullscreenElement, null);
        assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), true);
        assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
        assert.equal(f.boton.getAttribute('aria-label'), 'Restaurar mapa');
        assert.equal(f.boton.getAttribute('aria-busy'), 'false');
        assert.equal(f.boton.disabled, false);
        assert.equal(f.estado.hidden, false);
        assert.equal(f.estado.textContent, 'Mapa ampliado en esta página');
        assert.doesNotMatch(f.estado.textContent, /detalle interno/);
        assert.deepEqual(f.llamadas, {entrar: 1, salir: 0, redimensionar: 1});
        await f.boton.emitir('click');
        assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
        f.contenedor.requestFullscreen = f.entrar;
        await f.boton.emitir('click');
        assert.equal(f.boton.getAttribute('aria-pressed'), 'true');
        assert.equal(f.boton.getAttribute('aria-label'), 'Salir de pantalla completa');
        assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
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
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.cabecera.hasAttribute('inert'), false);
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

test('el respaldo aísla solo el exterior, mantiene accesibles los controles y Escape restaura el foco y los atributos previos', async t => {
    const f = escenario(t, {sinSolicitud: true});
    f.lateral.setAttribute('inert', 'preexistente'); f.pie.setAttribute('aria-hidden', 'true');
    f.documento.body.style.setProperty('overflow-x', 'scroll', 'important');
    f.documento.body.style.setProperty('overflow-y', 'auto');
    f.documento.documentElement.style.setProperty('overflow-y', 'visible');
    const estilosBody = new Map(f.documento.body.estilos), estilosHtml = new Map(f.documento.documentElement.estilos);
    f.enlace.focus({preventScroll: true});
    const antes = structuredClone(f.mapa);
    await f.boton.emitir('click');
    for (const n of [f.saltar, f.lateral, f.cabecera, f.pie, f.otro]) assert.equal(n.hasAttribute('inert'), true);
    assert.equal(f.boton.closest('[inert]'), null);
    assert.equal(f.documento.activeElement, f.boton);
    assert.deepEqual(f.boton.foco, {preventScroll: true});
    assert.equal(f.documento.body.style.getPropertyValue('overflow-x'), 'hidden');
    assert.equal(f.documento.body.style.getPropertyValue('overflow-y'), 'hidden');
    await f.documento.emitir('keydown', {key: 'Escape'});
    assert.equal(f.documento.ultimoEvento.defaultPrevented, true);
    assert.equal(f.documento.ultimoEvento.detenida, true);
    assert.equal(f.documento.activeElement, f.enlace);
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.contenedor.getAttribute('aria-label'), 'Mapa ambiental y controles');
    for (const n of [f.saltar, f.cabecera, f.pie, f.otro]) assert.equal(n.hasAttribute('inert'), false);
    assert.equal(f.lateral.getAttribute('inert'), 'preexistente');
    assert.equal(f.pie.getAttribute('aria-hidden'), 'true');
    assert.deepEqual(f.documento.body.estilos, estilosBody);
    assert.deepEqual(f.documento.documentElement.estilos, estilosHtml);
    assert.equal(f.contenedor.mapa, f.mapa); assert.deepEqual(f.mapa, antes);
    assert.deepEqual(f.llamadas, {entrar: 0, salir: 0, redimensionar: 2});
});

test('el respaldo recupera foco después de que un rechazo nativo lo deje fuera mientras el botón estaba deshabilitado', async t => {
    const f = escenario(t);
    f.contenedor.requestFullscreen = () => { f.documento.activeElement = f.documento.body; return Promise.reject(new Error('rechazo')); };
    await f.boton.emitir('click');
    assert.equal(f.boton.disabled, false);
    assert.equal(f.documento.activeElement, f.boton);
    await f.documento.emitir('keydown', {key: 'Escape'});
    assert.equal(f.documento.activeElement, f.boton);
});

test('un foco exterior inesperado vuelve al control del mapa solo mientras está ampliado dentro de la página', async t => {
    const f = escenario(t, {sinSolicitud: true});
    await f.boton.emitir('click');
    f.documento.activeElement = f.enlace;
    await f.documento.emitir('focusin', {target: f.enlace});
    assert.equal(f.documento.activeElement, f.boton);
    await f.boton.emitir('click');
    f.documento.activeElement = f.enlace;
    await f.documento.emitir('focusin', {target: f.enlace});
    assert.equal(f.documento.activeElement, f.enlace);
});

test('la limpieza del respaldo restaura todo lo propio sin deshacer cambios posteriores de otro componente', async t => {
    const f = escenario(t, {sinSolicitud: true});
    f.contenedor.setAttribute('aria-label', 'Nombre previo');
    await f.boton.emitir('click');
    f.cabecera.setAttribute('inert', 'otro propietario');
    f.documento.body.style.setProperty('overflow-x', 'scroll');
    f.control.cancelar(); f.control.cancelar();
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.contenedor.getAttribute('aria-label'), 'Nombre previo');
    assert.equal(f.cabecera.getAttribute('inert'), 'otro propietario');
    assert.equal(f.pie.hasAttribute('inert'), false);
    assert.equal(f.documento.body.style.getPropertyValue('overflow-x'), 'scroll');
    assert.equal(f.documento.body.style.getPropertyValue('overflow-y'), '');
    assert.equal(f.documento.documentElement.style.getPropertyValue('overflow-x'), '');
    assert.equal(f.estado.hidden, true);
    assert.equal(f.documento.oyentes('keydown'), 0);
    assert.equal(f.documento.oyentes('focusin'), 0);
    assert.equal(f.llamadas.salir, 0);
});

test('una pantalla completa ajena sobrevenida retira el respaldo sin cerrarla ni quitarle el foco', async t => {
    const f = escenario(t, {sinSolicitud: true});
    f.enlace.focus(); await f.boton.emitir('click');
    f.documento.fullscreenElement = f.ajeno; f.documento.activeElement = f.ajeno;
    await f.documento.emitir('fullscreenchange');
    assert.equal(f.documento.fullscreenElement, f.ajeno);
    assert.equal(f.documento.activeElement, f.ajeno);
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.cabecera.hasAttribute('inert'), false);
    assert.equal(f.boton.disabled, true);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
    assert.deepEqual(f.llamadas, {entrar: 0, salir: 0, redimensionar: 2});
});

test('una transición real a modo nativo retira clase e inert del respaldo y luego usa la salida nativa', async t => {
    const f = escenario(t);
    f.contenedor.requestFullscreen = () => Promise.reject(new Error('rechazo'));
    await f.boton.emitir('click');
    f.documento.fullscreenElement = f.contenedor; await f.documento.emitir('fullscreenchange');
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.cabecera.hasAttribute('inert'), false);
    assert.equal(f.boton.getAttribute('aria-label'), 'Salir de pantalla completa');
    assert.equal(f.estado.hidden, true);
    await f.boton.emitir('click');
    assert.equal(f.llamadas.salir, 1);
    assert.equal(f.boton.getAttribute('aria-pressed'), 'false');
});

for (const retirado of ['contenedor', 'boton', 'estado']) test(`un rechazo tardío con ${retirado} desconectado nunca activa el respaldo`, async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => pendiente.promesa;
    const entrada = f.boton.emitir('click');
    f[retirado].isConnected = false;
    pendiente.rechazar(new Error('rechazo tardío')); await entrada;
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.cabecera.hasAttribute('inert'), false);
    assert.equal(f.llamadas.redimensionar, 0);
    assert.equal(f.boton.disabled, true);
});

test('un rechazo nativo cuando ya hay pantalla completa ajena no activa el respaldo', async t => {
    const f = escenario(t), pendiente = diferida();
    f.contenedor.requestFullscreen = () => pendiente.promesa;
    const entrada = f.boton.emitir('click');
    f.documento.fullscreenElement = f.ajeno;
    pendiente.rechazar(new Error('rechazo')); await entrada;
    assert.equal(f.contenedor.classList.contains('monitoreo-ampliado'), false);
    assert.equal(f.documento.fullscreenElement, f.ajeno);
    assert.equal(f.llamadas.salir, 0);
    assert.equal(f.boton.disabled, true);
});
