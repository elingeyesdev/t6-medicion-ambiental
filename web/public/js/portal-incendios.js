import { iniciarMapaBase } from './mapas/base.js';

const L = window.L;
const avisos = JSON.parse(document.getElementById('datos-portal-mapa').textContent);
const mapa = L.map('portal-mapa', { scrollWheelZoom: false }).setView([-17.5, -62.1], 7);
if (!iniciarMapaBase(mapa, () => {}, null)) {
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(mapa);
}
const puntos = L.featureGroup().addTo(mapa);
const colores = { 'Por verificar': '#a34225', 'En verificación': '#2d6054' };

for (const aviso of avisos) {
    const ficha = document.createElement('div');
    const enlace = document.createElement('a');
    enlace.href = aviso.url;
    enlace.textContent = aviso.nombre;
    const estado = document.createElement('p');
    estado.textContent = aviso.estado;
    ficha.append(enlace, estado);
    L.circleMarker([aviso.lat, aviso.lon], {
        radius: 7, color: '#fff', weight: 2,
        fillColor: colores[aviso.estado] ?? '#5d625d',
        fillOpacity: 0.95,
    }).bindPopup(ficha).addTo(puntos);
}

if (avisos.length) mapa.fitBounds(puntos.getBounds(), { padding: [36, 36], maxZoom: 12 });
