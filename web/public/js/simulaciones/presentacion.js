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

    for (const [i, panel] of paneles.entries()) {
        const zona = document.getElementById(`sim-zona-${i}`);
        const escenario = panel.querySelector('.sim-escenario');
        const actualizar = () => {
            panel.querySelector('[data-zona-nombre]').textContent = zona.value ? 'en ' + zona.selectedOptions[0].textContent : 'en Zona ' + (i + 1);
            panel.querySelector('[data-metrica-nombre]').textContent = metrica.selectedOptions[0].textContent.split(' (')[0];
            if (escenario.value === 'personalizado') panel.querySelector('.sim-ajustes').open = true;
        };
        zona.addEventListener('change', actualizar);
        escenario.addEventListener('change', actualizar);
        metrica.addEventListener('change', actualizar);
        actualizar();
    }
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
