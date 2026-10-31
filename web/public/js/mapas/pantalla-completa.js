const controles = new WeakMap();
const entradasPendientes = new WeakSet();
const salidasPendientes = new WeakSet();

export function iniciarPantallaCompleta({contenedor, boton, estado, redimensionar = () => {}}) {
    if (!contenedor || !boton || !estado) return {cancelar() {}};
    if (controles.has(boton)) return controles.get(boton).control;
    const documento = contenedor.ownerDocument;
    const disponible = typeof contenedor.requestFullscreen === 'function' && typeof documento.exitFullscreen === 'function' && documento.fullscreenEnabled !== false;
    let activo = true, pendiente = false;
    let propiaAnterior = documento.fullscreenElement === contenedor;

    function avisar(mensaje = '') {
        estado.textContent = mensaje;
        estado.hidden = !mensaje;
    }

    function actualizar() {
        if (!activo) return;
        const propia = documento.fullscreenElement === contenedor;
        const ajena = !!documento.fullscreenElement && !propia;
        const ocupada = pendiente || entradasPendientes.has(contenedor) || salidasPendientes.has(contenedor);
        const nombre = disponible ? (propia ? 'Salir de pantalla completa' : 'Entrar en pantalla completa') : 'Pantalla completa no disponible';
        boton.setAttribute('aria-label', nombre);
        boton.setAttribute('title', ajena ? 'Cierra la otra pantalla completa para ampliar este mapa.' : nombre);
        boton.setAttribute('aria-pressed', String(propia));
        boton.setAttribute('aria-busy', String(ocupada));
        boton.disabled = !disponible || ocupada || ajena;
        if (propia !== propiaAnterior) {
            propiaAnterior = propia;
            avisar();
            redimensionar();
        }
    }

    async function salirPropia() {
        if (salidasPendientes.has(contenedor) || documento.fullscreenElement !== contenedor) return;
        salidasPendientes.add(contenedor);
        try { await documento.exitFullscreen(); }
        finally { salidasPendientes.delete(contenedor); controles.get(boton)?.actualizar(); }
    }

    async function alternar() {
        if (!activo || pendiente || !disponible || entradasPendientes.has(contenedor) || salidasPendientes.has(contenedor)) return;
        if (documento.fullscreenElement && documento.fullscreenElement !== contenedor) { actualizar(); return; }
        const salir = documento.fullscreenElement === contenedor;
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
            if (activo) avisar(salir ? 'No se pudo salir de pantalla completa. Pulsa Escape o inténtalo de nuevo.' : 'No se pudo abrir la pantalla completa. Vuelve a intentarlo.');
        } finally {
            pendiente = false;
            actualizar();
            controles.get(boton)?.actualizar();
        }
    }

    function cancelar() {
        if (!activo) return;
        activo = false;
        controles.delete(boton);
        boton.removeEventListener('click', alternar);
        documento.removeEventListener('fullscreenchange', actualizar);
        boton.disabled = true; boton.hidden = true; avisar();
        void salirPropia().catch(() => {});
    }

    const control = {cancelar};
    controles.set(boton, {control, actualizar});
    boton.hidden = false;
    boton.addEventListener('click', alternar);
    documento.addEventListener('fullscreenchange', actualizar);
    actualizar();
    if (!disponible) avisar('La pantalla completa no está disponible en este navegador.');
    return control;
}
