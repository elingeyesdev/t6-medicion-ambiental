const controles = new WeakMap();
const entradasPendientes = new WeakSet();
const salidasPendientes = new WeakSet();

export function iniciarPantallaCompleta({contenedor, boton, estado, redimensionar = () => {}}) {
    if (!contenedor || !boton || !estado) return {cancelar() {}};
    if (controles.has(boton)) return controles.get(boton).control;
    const documento = contenedor.ownerDocument;
    const disponible = typeof contenedor.requestFullscreen === 'function' && typeof documento.exitFullscreen === 'function' && documento.fullscreenEnabled !== false;
    let activo = true, pendiente = false, pagina = null;
    let modoAnterior = documento.fullscreenElement === contenedor ? 'nativa' : 'normal';

    function avisar(mensaje = '', fallo = false) {
        estado.textContent = mensaje;
        estado.hidden = !mensaje;
        estado.classList.toggle('alert-warning', fallo);
        estado.classList.toggle('alert-info', !fallo);
    }

    function conectados() {
        return contenedor.isConnected && boton.isConnected && estado.isConnected;
    }

    function comunicarAmpliacion(activo) {
        // Contrato síncrono del respaldo CSS con navegación: {activo: boolean}.
        // Al entrar, Nav asume su propio inert antes del aislamiento genérico;
        // al salir, lo recalcula antes de intentar devolver el foco.
        documento.dispatchEvent(new CustomEvent('monitoreo-ampliacion-cambiada', {detail: {activo}}));
    }

    function ampliarPagina(foco = documento.activeElement) {
        if (!activo || !conectados() || pagina || documento.fullscreenElement) return;
        const restaurar = [];
        const clasePrevia = contenedor.classList.contains('monitoreo-ampliado');
        const nombrePrevio = contenedor.getAttribute('aria-label');
        contenedor.classList.add('monitoreo-ampliado');
        contenedor.setAttribute('aria-label', 'Mapa ampliado en esta página');
        restaurar.push(() => {
            if (!clasePrevia) contenedor.classList.remove('monitoreo-ampliado');
            if (contenedor.getAttribute('aria-label') === 'Mapa ampliado en esta página') {
                if (nombrePrevio === null) contenedor.removeAttribute('aria-label');
                else contenedor.setAttribute('aria-label', nombrePrevio);
            }
        });
        comunicarAmpliacion(true);
        // Solo se aíslan los hermanos de la rama del mapa, nunca sus controles.
        for (let rama = contenedor; rama.parentElement && rama !== documento.body; rama = rama.parentElement) {
            for (const otro of rama.parentElement.children) {
                if (otro === rama || otro.hasAttribute('inert')) continue;
                otro.setAttribute('inert', '');
                restaurar.push(() => { if (otro.getAttribute('inert') === '') otro.removeAttribute('inert'); });
            }
        }
        for (const elemento of [documento.documentElement, documento.body]) {
            for (const propiedad of ['overflow-x', 'overflow-y']) {
                const estilo = elemento.style;
                const valor = estilo.getPropertyValue(propiedad), prioridad = estilo.getPropertyPriority(propiedad);
                estilo.setProperty(propiedad, 'hidden', 'important');
                restaurar.push(() => {
                    if (estilo.getPropertyValue(propiedad) !== 'hidden' || estilo.getPropertyPriority(propiedad) !== 'important') return;
                    if (valor) estilo.setProperty(propiedad, valor, prioridad);
                    else estilo.removeProperty(propiedad);
                });
            }
        }
        pagina = {restaurar, foco};
    }

    function restaurarPagina(devolverFoco = true) {
        if (!pagina) return;
        const {restaurar, foco} = pagina;
        const dentro = contenedor.contains(documento.activeElement);
        pagina = null;
        for (const restauracion of restaurar.reverse()) restauracion();
        comunicarAmpliacion(false);
        if (devolverFoco && dentro && !documento.fullscreenElement && foco?.isConnected && !foco.closest('[inert]')) foco.focus({preventScroll: true});
    }

    function actualizar() {
        if (!activo) return;
        if (pagina && (documento.fullscreenElement || !conectados())) restaurarPagina(false);
        const propia = documento.fullscreenElement === contenedor;
        const ajena = !!documento.fullscreenElement && !propia;
        const modo = propia ? 'nativa' : pagina ? 'pagina' : 'normal';
        const ocupada = pendiente || entradasPendientes.has(contenedor) || salidasPendientes.has(contenedor);
        const nombre = propia ? 'Salir de pantalla completa' : pagina ? 'Restaurar mapa' : disponible ? 'Entrar en pantalla completa' : 'Ampliar mapa';
        boton.setAttribute('aria-label', nombre);
        boton.setAttribute('title', ajena ? 'Cierra la otra pantalla completa para ampliar este mapa.' : nombre);
        boton.setAttribute('aria-pressed', String(propia || !!pagina));
        boton.setAttribute('aria-busy', String(ocupada));
        boton.disabled = ocupada || ajena || !conectados();
        if (pagina && !boton.disabled && !contenedor.contains(documento.activeElement)) boton.focus({preventScroll: true});
        if (modo !== modoAnterior) {
            modoAnterior = modo;
            avisar(pagina ? 'Mapa ampliado en esta página' : '');
            redimensionar();
        }
    }

    async function salirPropia() {
        if (salidasPendientes.has(contenedor) || documento.fullscreenElement !== contenedor) return;
        salidasPendientes.add(contenedor);
        controles.get(boton)?.actualizar();
        try { await documento.exitFullscreen(); }
        finally { salidasPendientes.delete(contenedor); controles.get(boton)?.actualizar(); }
    }

    async function alternar() {
        if (!activo || !conectados() || pendiente || entradasPendientes.has(contenedor) || salidasPendientes.has(contenedor)) return;
        if (documento.fullscreenElement && documento.fullscreenElement !== contenedor) { actualizar(); return; }
        if (pagina && !documento.fullscreenElement) { restaurarPagina(); actualizar(); return; }
        if (!disponible && !documento.fullscreenElement) { ampliarPagina(); actualizar(); return; }
        const salir = documento.fullscreenElement === contenedor;
        const focoEntrada = documento.activeElement;
        pendiente = true; avisar(); actualizar();
        try {
            // Se llama antes del primer await para conservar el gesto del usuario.
            if (salir) await salirPropia();
            else {
                entradasPendientes.add(contenedor);
                try { await contenedor.requestFullscreen(); }
                finally { entradasPendientes.delete(contenedor); }
            }
            if (!activo && !controles.has(boton)) await salirPropia();
        } catch {
            if (activo && conectados()) {
                if (salir) avisar('No se pudo salir de pantalla completa. Pulsa Escape o inténtalo de nuevo.', true);
                else ampliarPagina(focoEntrada);
            }
        } finally {
            pendiente = false;
            actualizar();
            controles.get(boton)?.actualizar();
        }
    }

    async function tecla(evento) {
        if (evento.key !== 'Escape') return;
        const propia = documento.fullscreenElement === contenedor;
        if (!propia && (!pagina || documento.fullscreenElement)) return;
        evento.preventDefault(); evento.stopPropagation();
        if (propia) {
            try { await salirPropia(); }
            catch {
                if (activo && conectados() && documento.fullscreenElement === contenedor) {
                    avisar('No se pudo salir de pantalla completa. Usa el botón para reintentar.', true);
                }
            }
            return;
        }
        restaurarPagina(); actualizar();
    }

    function enfocar(evento) {
        if (pagina && !documento.fullscreenElement && conectados() && !contenedor.contains(evento.target)) boton.focus({preventScroll: true});
    }

    function cancelar() {
        if (!activo) return;
        activo = false;
        controles.delete(boton);
        boton.removeEventListener('click', alternar);
        documento.removeEventListener('fullscreenchange', actualizar);
        documento.removeEventListener('keydown', tecla, true);
        documento.removeEventListener('focusin', enfocar);
        restaurarPagina();
        boton.disabled = true; boton.hidden = true; avisar();
        void salirPropia().catch(() => {});
    }

    const control = {cancelar};
    controles.set(boton, {control, actualizar});
    boton.hidden = false;
    boton.addEventListener('click', alternar);
    documento.addEventListener('fullscreenchange', actualizar);
    documento.addEventListener('keydown', tecla, true);
    documento.addEventListener('focusin', enfocar);
    actualizar();
    return control;
}
