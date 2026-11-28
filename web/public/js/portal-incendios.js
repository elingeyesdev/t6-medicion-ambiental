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

if (avisos.length === 1) {
    mapa.recordarParaCentrar?.([avisos[0].lat, avisos[0].lon]);
    mapa.centrarSeleccion?.();
} else if (avisos.length > 1) {
    mapa.fitBounds(puntos.getBounds(), { padding: [36, 36], maxZoom: 12 });
}

const trayectoria = document.querySelector('.trayectoria-aviso');
if (trayectoria && avisos.length === 1) {
    const marcador = puntos.getLayers()[0];
    const [aviso] = avisos;
    const pasos = trayectoria.querySelectorAll('details');
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    const mostrarPunto = (etiqueta) => {
        const ficha = document.createElement('div');
        const titulo = document.createElement('strong');
        titulo.textContent = aviso.nombre;
        const evento = document.createElement('p');
        evento.textContent = etiqueta;
        ficha.append(titulo, evento);
        mapa.setView([aviso.lat, aviso.lon], 11, { animate: !sinMovimiento.matches });
        marcador.setPopupContent(ficha).openPopup();
    };

    for (const paso of pasos) {
        const boton = paso.querySelector('[data-enfocar-hito]');
        if (boton) {
            boton.hidden = false;
            boton.addEventListener('click', () => {
                mostrarPunto(boton.dataset.etiqueta);
                mapa.getContainer().scrollIntoView({ block: 'nearest', behavior: sinMovimiento.matches ? 'instant' : 'smooth' });
                mapa.getContainer().focus({ preventScroll: true });
            });
        }
        paso.addEventListener('toggle', () => {
            if (!paso.open) return;
            for (const otro of pasos) {
                if (otro !== paso) otro.open = false;
            }
            if (boton) mostrarPunto(boton.dataset.etiqueta);
        });
    }
}
