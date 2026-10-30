const header = document.querySelector('[data-landing-header]');
const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 20);
window.addEventListener('scroll', updateHeader, { passive: true });
updateHeader();

const mapElement = document.getElementById('portada-mapa');
let config = {};
try { config = JSON.parse(document.getElementById('configuracion-mapa')?.textContent || '{}'); } catch { /* Imagen de referencia disponible. */ }
if (mapElement && window.L && /^pk\.[A-Za-z0-9._-]+$/.test(config.tokenPublico || '')) {
    const map = window.L.map(mapElement, { zoomControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, boxZoom: false, keyboard: false }).setView([-15, -60], 3);
    window.L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/dark-v11/tiles/512/{z}/{x}/{y}?access_token=' + encodeURIComponent(config.tokenPublico), {
        tileSize: 512, zoomOffset: -1, maxZoom: 22,
        attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    const marca = window.L.control({position: 'bottomleft'});
    marca.onAdd = () => {
        const enlace = document.createElement('a');
        enlace.href = 'https://www.mapbox.com/about/maps/';
        enlace.className = 'mapbox-wordmark';
        enlace.setAttribute('aria-label', 'Mapbox');
        const imagen = document.createElement('img');
        imagen.src = '/vendor/mapbox/mapbox-logo.svg'; imagen.alt = 'Mapbox'; imagen.width = 88; imagen.height = 24;
        enlace.append(imagen);
        return enlace;
    };
    marca.addTo(map);
}
