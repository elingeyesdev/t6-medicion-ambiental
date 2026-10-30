// Herramientas flotantes del mapa de EnviroSense; las consultas conservan su API.
(() => {
    const panel = document.getElementById('ajustes-monitoreo');
    const abrir = document.getElementById('abrir-ajustes-mapa');
    if (!panel || !abrir) return;
    function mostrar(visible) {
        panel.hidden = !visible;
        abrir.setAttribute('aria-expanded', String(visible));
        if (visible) panel.querySelector('[data-cerrar-ajustes]').focus();
    }
    function pestana(nombre) {
        for (const boton of panel.querySelectorAll('[data-opcion-mapa]')) boton.setAttribute('aria-pressed', String(boton.dataset.opcionMapa === nombre));
        for (const bloque of panel.querySelectorAll('[data-contenido-mapa]')) bloque.hidden = bloque.dataset.contenidoMapa !== nombre;
    }
    abrir.addEventListener('click', () => mostrar(panel.hidden));
    panel.querySelector('[data-cerrar-ajustes]').addEventListener('click', () => { mostrar(false); abrir.focus(); });
    for (const boton of panel.querySelectorAll('[data-opcion-mapa]')) boton.addEventListener('click', () => pestana(boton.dataset.opcionMapa));
    document.getElementById('abrir-buscador-mapa').addEventListener('click', () => {
        pestana('preferencias'); mostrar(true); document.getElementById('nombre-lugar').focus();
    });
    document.getElementById('abrir-historial-mapa').addEventListener('click', () => {
        document.getElementById('filtros-monitoreo').open = true;
        document.getElementById('periodo-monitoreo').open = true;
        document.getElementById('mon-desde').focus();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { mostrar(false); abrir.focus(); } });
})();
