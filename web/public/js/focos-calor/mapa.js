const colores = { alta: '#bd671d', nominal: '#25856b', baja: '#ad9a53', desconocida: '#788084' };

/** Leaflet solo representa; la pertenencia y las métricas ya vienen de la API. */
export class MapaFocos {
    constructor(contenedor, alSeleccionar, avisar) {
        this.contenedor = contenedor;
        this.alSeleccionar = alSeleccionar;
        this.avisar = avisar;
        this.marcadores = new Map();
    }

    iniciar() {
        if (this.mapa) return true;
        if (!window.L) { this.avisar('No se pudo iniciar el mapa. Puedes consultar todas las detecciones de esta página en el listado.'); return false; }
        const L = window.L;
        this.mapa = L.map(this.contenedor, { scrollWheelZoom: false, zoomAnimation: !matchMedia('(prefers-reduced-motion: reduce)').matches }).setView([-17.5, -61.5], 6);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).on('tileerror', () => this.avisar('Algunas imágenes del mapa base no pudieron cargarse. Los límites, puntos y el listado siguen disponibles.')).addTo(this.mapa);
        this.puntos = L.layerGroup().addTo(this.mapa);
        return true;
    }

    mostrar(datos, limite, errorMapa) {
        this.avisar(errorMapa);
        try {
            if (!this.iniciar()) return;
            const L = window.L;
            this.puntos.clearLayers(); this.marcadores.clear();
            if (this.limite) this.limite.remove();
            this.limite = limite ? L.geoJSON(limite, { interactive: false, style: { color: '#49705b', weight: 1.6, fillColor: '#c6d9bc', fillOpacity: 0.15 } }).addTo(this.mapa) : null;
            datos.forEach(foco => {
                const g = foco.geometria;
                if (g?.type !== 'Point' || !Array.isArray(g.coordinates) || !g.coordinates.every(Number.isFinite)) return;
                const [x, y] = g.coordinates;
                const punto = L.circleMarker([y, x], { radius: 6, fillColor: colores[foco.atributos.confianza] ?? colores.desconocida, color: '#fff', weight: 1.2, fillOpacity: 0.9, className: 'marcador-foco' }).addTo(this.puntos);
                const etiqueta = document.createElement('span');
                etiqueta.textContent = `Detección ${foco.identificador.slice(-8)} · confianza ${foco.atributos.confianza}`;
                punto.bindTooltip(etiqueta);
                punto.on('click', () => this.alSeleccionar(foco.identificador, punto.getElement()));
                const elemento = punto.getElement();
                if (elemento) {
                    elemento.setAttribute('tabindex', '0'); elemento.setAttribute('role', 'button'); elemento.setAttribute('aria-label', etiqueta.textContent);
                    elemento.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.alSeleccionar(foco.identificador, elemento); } });
                }
                this.marcadores.set(foco.identificador, punto);
            });
            requestAnimationFrame(() => { this.mapa.invalidateSize(); this.centrar(); });
        } catch { this.avisar('El mapa no pudo dibujarse. El listado y los detalles de la consulta siguen disponibles.'); }
    }

    centrar() {
        if (this.limite) this.mapa?.fitBounds(this.limite.getBounds(), { padding: [18, 18], maxZoom: 11, animate: false });
        else if (this.marcadores.size) this.mapa?.fitBounds(window.L.featureGroup([...this.marcadores.values()]).getBounds(), { padding: [25, 25], maxZoom: 10, animate: false });
    }

    seleccionar(id) {
        this.marcadores.forEach((punto, clave) => punto.setStyle({ radius: clave === id ? 9 : 6, weight: clave === id ? 3 : 1.2, color: clave === id ? '#173d2e' : '#fff' }));
        const punto = this.marcadores.get(id);
        if (punto) {
            if (!this.mapa.getBounds().contains(punto.getLatLng())) this.mapa.panTo(punto.getLatLng(), { animate: false });
            punto.bringToFront(); punto.openTooltip();
        }
    }
}
