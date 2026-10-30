// Presentación de la consola: usa los formularios y eventos existentes, sin nuevas consultas.
import { presentacion } from '../datos/unidades.js';

const pagina = document.querySelector('.sim-contenido');
if (pagina) {
    const metrica = document.getElementById('sim-metrica');
    const formulario = pagina.querySelector('.sim-configuracion');
    if (formulario) {
    const catalogo = JSON.parse(document.getElementById('catalogo-escenarios').textContent);
    const paneles = [...formulario.querySelectorAll('[data-zona-panel]')];
    const tabs = [...formulario.querySelectorAll('[data-zona-tab]')];
    const tablist = formulario.querySelector('[data-zona-tabs]');

    function elegirZona(indice, enfocar = false) {
        for (const [i, tab] of tabs.entries()) {
            tab.setAttribute('aria-selected', String(i === indice));
            tab.tabIndex = i === indice ? 0 : -1;
            paneles[i].hidden = i !== indice;
        }
        if (enfocar) tabs[indice].focus();
    }
    tablist.hidden = false;
    tablist.setAttribute('role', 'tablist');
    for (const [i, tab] of tabs.entries()) {
        tab.setAttribute('role', 'tab');
        paneles[i].setAttribute('role', 'tabpanel');
        paneles[i].setAttribute('aria-labelledby', tab.id);
        tab.addEventListener('click', () => elegirZona(i));
        tab.addEventListener('keydown', evento => {
            const siguiente = {ArrowRight: (i + 1) % tabs.length, ArrowLeft: (i + tabs.length - 1) % tabs.length, Home: 0, End: tabs.length - 1}[evento.key];
            if (siguiente === undefined) return;
            evento.preventDefault();
            elegirZona(siguiente, true);
        });
    }
    elegirZona(0);
    formulario.addEventListener('invalid', evento => {
        const panel = evento.target.closest('[data-zona-panel]');
        if (panel) elegirZona(Number(panel.dataset.zonaPanel));
        const ajustes = evento.target.closest('details');
        if (ajustes) ajustes.open = true;
    }, true);

    const curvas = {lineal: 'Cambio gradual', pico: 'Subida y bajada', exponencial: 'Aumento acelerado'};
    function actualizarZona(panel) {
        const escenario = panel.querySelector('.sim-escenario');
        const nivel = panel.querySelector('[name$="[nivel]"]');
        for (const boton of panel.querySelectorAll('[data-escenario]')) {
            boton.setAttribute('aria-pressed', String(boton.dataset.escenario === escenario.value));
            const caso = catalogo[metrica.value]?.find(e => e.id === boton.dataset.escenario);
            const valores = caso?.niveles[nivel.value];
            const rango = boton.querySelector('.msim-escenario-rango');
            const unidad = pagina.querySelector(`[data-promedio="${metrica.value}"]`)?.dataset.unidad ?? '';
            if (rango && valores) rango.textContent = `${valores.inicio} → ${valores.fin} ${unidad}`;
        }
        for (const boton of panel.querySelectorAll('[data-nivel]')) boton.setAttribute('aria-pressed', String(boton.dataset.nivel === nivel.value));
        if (escenario.value === 'personalizado') panel.querySelector('.sim-ajustes').open = true;
    }
    function crearTarjetas(panel) {
        const escenario = panel.querySelector('.sim-escenario');
        const tarjetas = panel.querySelector('[data-escenario-tarjetas]');
        const casos = [...(catalogo[metrica.value] ?? []), {id: 'personalizado', nombre: 'Personalizado'}];
        tarjetas.replaceChildren(...casos.map(caso => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'msim-escenario-card';
            boton.dataset.escenario = caso.id;
            if (caso.id === 'personalizado') boton.classList.add('msim-escenario-card--personalizado');
            else {
                const icono = document.createElement('span');
                icono.className = 'msim-escenario-icon';
                const svg = pagina.querySelector(`[data-promedio="${metrica.value}"] .sim-icono`);
                if (svg) icono.append(svg.cloneNode(true));
                boton.append(icono);
            }
            const nombre = document.createElement('span');
            nombre.className = 'msim-escenario-nombre';
            nombre.textContent = caso.nombre;
            const descripcion = document.createElement('span');
            descripcion.className = 'msim-escenario-desc';
            descripcion.textContent = curvas[caso.curva] ?? 'Define tus propios valores';
            boton.append(nombre, descripcion);
            if (caso.niveles) {
                const rango = document.createElement('span');
                rango.className = 'msim-escenario-rango';
                boton.append(rango);
            }
            boton.addEventListener('click', () => {
                escenario.value = caso.id;
                escenario.dispatchEvent(new Event('change', {bubbles: true}));
            });
            return boton;
        }));
        tarjetas.hidden = false;
        // Los selects siguen siendo los campos que envía el formulario.
        panel.querySelector('.sim-campo-escenario').hidden = true;
        actualizarZona(panel);
    }
    for (const [i, panel] of paneles.entries()) {
        const zona = document.getElementById(`sim-zona-${i}`);
        const actualizarNombre = () => {
            panel.querySelector('[data-zona-nombre]').textContent = zona.value ? `en ${zona.selectedOptions[0].textContent}` : `en Zona ${i + 1}`;
        };
        zona.addEventListener('change', actualizarNombre);
        actualizarNombre();
        panel.querySelector('.sim-escenario').addEventListener('change', () => actualizarZona(panel));
        const nivel = panel.querySelector('[name$="[nivel]"]');
        nivel.addEventListener('change', () => actualizarZona(panel));
        const grupo = panel.querySelector('[data-nivel-botones]');
        grupo.hidden = false;
        grupo.setAttribute('role', 'group');
        grupo.setAttribute('aria-label', `Intensidad del escenario · Zona ${i + 1}`);
        // Conserva el título del selector original junto a los botones de intensidad.
        panel.querySelector('.sim-campo-nivel label').removeAttribute('for');
        nivel.hidden = true;
        for (const boton of grupo.querySelectorAll('[data-nivel]')) boton.addEventListener('click', () => {
            nivel.value = boton.dataset.nivel;
            nivel.dispatchEvent(new Event('change', {bubbles: true}));
        });
        crearTarjetas(panel);
    }
    metrica.addEventListener('change', () => queueMicrotask(() => {
        // inicio.js actualiza primero los escenarios del catálogo en cada select.
        for (const panel of paneles) {
            panel.querySelector('[data-metrica-nombre]').textContent = metrica.selectedOptions[0].textContent.replace(/ \([^)]*\)$/, '');
            crearTarjetas(panel);
        }
    }));
    }

    let ultimoEstado = null;
    function actualizarResumen(estado) {
        ultimoEstado = estado;
        const puntos = estado.puntos ?? [];
        pagina.querySelector('[data-continua-paso]').textContent = estado.paso ?? '—';
        const paso = pagina.querySelector('[data-consola-paso]');
        if (paso) paso.textContent = estado.paso === undefined ? 'Tiempo real y escenarios' : `Paso ${estado.paso}`;
        pagina.querySelector('[data-continua-puntos]').textContent = puntos.length;
        pagina.querySelector('[data-continua-instante]').textContent = estado.instante ? new Date(estado.instante).toLocaleTimeString('es-BO', {timeZone: 'America/La_Paz'}) : '—';
        const intervalo = pagina.querySelector('[data-continua-intervalo]');
        if (intervalo) intervalo.textContent = estado.intervalo_ms ? `Cada ${estado.intervalo_ms / 1000} s` : 'Recorrido sincronizado';
        const activo = estado.estado === 'activa';
        pagina.querySelector('.sim-status-dot').classList.toggle('sim-status-dot--active', activo);
        const icono = pagina.querySelector(`[data-control="${activo ? 'iniciar' : 'detener'}"] .sim-icono`);
        if (icono) pagina.querySelector('.sim-status-dot').replaceChildren(icono.cloneNode(true));
        for (const tarjeta of pagina.querySelectorAll('[data-promedio]')) {
            const valores = puntos.map(p => p.valores[tarjeta.dataset.promedio]).filter(v => typeof v === 'number' && Number.isFinite(v));
            tarjeta.querySelector('.sim-summary-value').textContent = valores.length ? presentacion.texto(tarjeta.dataset.promedio, valores.reduce((a, b) => a + b, 0) / valores.length, tarjeta.dataset.unidad) : '—';
            tarjeta.querySelector('.sim-summary-tag').textContent = valores.length ? `Promedio · ${valores.length} localidades` : 'Sin lecturas simuladas';
        }
    }
    pagina.addEventListener('simulacion-actualizada', evento => actualizarResumen(evento.detail));
    document.addEventListener('unidades-cambiadas', () => { if (ultimoEstado) actualizarResumen(ultimoEstado); });
}
