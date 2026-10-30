const estilos = Object.freeze({ claro: 'light-v11', relieve: 'outdoors-v12', satelite: 'satellite-streets-v12', calles: 'streets-v12' });
const atribucion = '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · <a href="https://apps.mapbox.com/feedback/" target="_blank" rel="noopener">Improve this map</a>';

export function leerConfiguracionMapa(elemento) {
    try {
        const c = JSON.parse(elemento?.textContent ?? '{}');
        if (c.proveedor !== 'mapbox' || typeof c.tokenPublico !== 'string' || !/^pk\.[A-Za-z0-9._-]+$/.test(c.tokenPublico)) return null;
        return { tokenPublico: c.tokenPublico, baseInicial: Object.hasOwn(estilos, c.baseInicial) ? c.baseInicial : 'relieve' };
    } catch { return null; }
}

/** Cambia únicamente las imágenes de fondo; no toca puntos, límites ni selección. */
export function iniciarMapaBase(mapa, avisar, selectorId) {
    const configuracion = leerConfiguracionMapa(document.getElementById('configuracion-mapa'));
    const selector = document.getElementById(selectorId);
    if (!configuracion) {
        avisar('El mapa base no está disponible. Los puntos, límites y la lista siguen disponibles.');
        return null;
    }
    const L = window.L;
    let actual = null, falloAvisado = false;
    const capas = new Map(Object.entries(estilos).map(([id, estilo]) => [id, L.tileLayer(
        `https://api.mapbox.com/styles/v1/mapbox/${estilo}/tiles/512/{z}/{x}/{y}?access_token=${encodeURIComponent(configuracion.tokenPublico)}`,
        { tileSize: 512, zoomOffset: -1, maxZoom: 22, attribution: atribucion + (id === 'satelite' ? ' © Maxar' : '') }
    ).on('tileerror', () => {
        if (!falloAvisado) { falloAvisado = true; avisar('No se cargaron algunas imágenes de Mapbox. Los puntos, límites y la lista siguen disponibles.'); }
    })]));
    const activar = id => {
        const capa = capas.get(id);
        if (!capa || capa === actual) return;
        falloAvisado = false;
        capa.addTo(mapa);
        if (actual) mapa.removeLayer(actual);
        actual = capa;
        if (selector) selector.value = id;
        try { localStorage.setItem('geocruz-base-mapa', id); } catch { /* Preferencia opcional. */ }
    };
    let inicial = configuracion.baseInicial;
    try { const guardada = localStorage.getItem('geocruz-base-mapa'); if (capas.has(guardada)) inicial = guardada; } catch { /* Preferencia opcional. */ }
    activar(inicial);
    if (selector) {
        selector.disabled = false;
        selector.addEventListener('change', () => activar(selector.value));
    }
    const marca = L.control({ position: 'bottomleft' });
    marca.onAdd = () => {
        const enlace = document.createElement('a');
        enlace.href = 'https://www.mapbox.com/about/maps/'; enlace.target = '_blank'; enlace.rel = 'noopener'; enlace.className = 'mapbox-wordmark'; enlace.setAttribute('aria-label', 'Mapbox');
        const logo = document.createElement('img'); logo.src = '/vendor/mapbox/mapbox-logo.svg'; logo.alt = 'Mapbox'; logo.width = 88; logo.height = 24;
        enlace.append(logo);
        return enlace;
    };
    marca.addTo(mapa);
    return { activar };
}
