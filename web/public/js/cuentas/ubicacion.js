import { iniciarMapaBase } from '../mapas/base.js';

const raiz = document.getElementById('cuenta-mapa');
if (raiz && window.L) {
    const L = window.L, latitud = document.getElementById('latitud'), longitud = document.getElementById('longitud');
    const estado = document.getElementById('estado-ubicacion');
    const guardada = latitud.value !== '' && longitud.value !== '';
    const mapa = L.map(raiz, { scrollWheelZoom: true }).setView(guardada ? [Number(latitud.value), Number(longitud.value)] : [-17.78, -63.18], guardada ? 11 : 5);
    iniciarMapaBase(mapa, () => { estado.textContent = 'No se pudo cargar el fondo del mapa. Intenta de nuevo más tarde.'; });
    let punto = null;
    const elegir = coordenada => {
        const lugar = L.latLng(Math.max(-90, Math.min(90, coordenada.lat)), ((coordenada.lng + 180) % 360 + 360) % 360 - 180);
        latitud.value = lugar.lat.toFixed(6); longitud.value = lugar.lng.toFixed(6);
        if (!punto) {
            punto = L.marker(lugar, { draggable: true, title: 'Tu ubicación', icon: L.divIcon({ className: 'punto-cuenta', html: '<span></span>', iconSize: [22, 22], iconAnchor: [11, 11] }) }).addTo(mapa);
            punto.on('dragend', () => elegir(punto.getLatLng()));
        } else punto.setLatLng(lugar);
        estado.textContent = 'Lugar elegido. Pulsa Guardar ubicación.';
    };
    if (guardada) { elegir(L.latLng(Number(latitud.value), Number(longitud.value))); estado.textContent = 'Tienes una ubicación guardada.'; }
    mapa.on('click', evento => elegir(evento.latlng));
    raiz.addEventListener('keydown', evento => { if (evento.key === 'Enter' && evento.target === raiz) { evento.preventDefault(); elegir(mapa.getCenter()); } });
    document.getElementById('quitar-ubicacion').addEventListener('click', () => {
        if (punto) mapa.removeLayer(punto);
        punto = null; latitud.value = ''; longitud.value = '';
        estado.textContent = 'Pulsa Guardar ubicación para quitar el lugar guardado.';
    });
}
