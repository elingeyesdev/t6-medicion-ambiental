import { categorias, categoria, numero, pedirJson } from './contrato.js';
import { iniciarMapaBase } from '../mapas/base.js';

/** Puntos en el centro de celda y contornos sin relleno. No interpola ni clasifica SPI. */
export class MapaSequias {
    constructor(base, version, seleccionar, avisar) {
        Object.assign(this, { base, version, seleccionar, avisar }); this.generacion = 0; this.porId = new Map();
    }
    async mostrar(respuesta) {
        const turno = ++this.generacion;
        this.control?.abort(); const control = new AbortController(); this.control = control;
        this.avisar(null);
        if (!window.L) { this.avisar('No se pudo iniciar el mapa. Consulta las mismas muestras en la lista.'); return; }
        const L = window.L;
        if (!this.mapa) {
            this.mapa = L.map('sq-mapa', { scrollWheelZoom: true, zoomAnimation: false, fadeAnimation: false }).setView([-17.5, -61.5], 6);
            this.baseVisual = iniciarMapaBase(this.mapa, mensaje => this.avisar(mensaje), 'base-mapa-sequias');
            this.puntos = L.layerGroup().addTo(this.mapa);
        }
        this.puntos.clearLayers(); this.porId.clear(); this.limite?.remove(); this.limite = null;
        const grupos = new Map();
        for (const d of respuesta.datos) {
            const clave = d.coordenadaCelda.join(',');
            if (!grupos.has(clave)) grupos.set(clave, []);
            grupos.get(clave).push(d);
        }
        for (const grupo of grupos.values()) {
            const d = grupo[0]; const [lon, lat] = d.coordenadaCelda;
            const color = grupo.every(m => m.spi.categoria === d.spi.categoria) ? categorias[d.spi.categoria]?.[1] ?? '#7d8183' : '#7d8183';
            const marcador = L.circleMarker([lat, lon], { radius: grupo.length > 1 ? 10 : 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: .95 }).addTo(this.puntos);
            const titulo = grupo.length > 1 ? `${grupo.length} muestras comparten esta celda. Elegir muestra` : `${d.idMuestra}: SPI ${numero(d.spi.valor)}, ${categoria(d)}. Abrir detalle`;
            const abrir = () => {
                if (grupo.length === 1) { this.seleccionar(d.idMuestra, marcador.getElement()); return; }
                const opciones = document.createElement('div');
                for (const m of grupo) {
                    const boton = document.createElement('button'); boton.type = 'button'; boton.className = 'sq-mapa-opcion'; boton.textContent = `${m.idMuestra} · ${categoria(m)}`;
                    boton.addEventListener('click', () => { this.seleccionar(m.idMuestra, marcador.getElement()); this.mapa.closePopup(); }); opciones.append(boton);
                }
                marcador.bindPopup(opciones).openPopup();
            };
            marcador.on('click', abrir);
            const elemento = marcador.getElement();
            if (elemento) {
                elemento.setAttribute('tabindex', '0'); elemento.setAttribute('role', 'button'); elemento.setAttribute('aria-label', titulo);
                elemento.addEventListener('keydown', e => { if (['Enter', ' '].includes(e.key)) { e.preventDefault(); abrir(); } });
            }
            for (const m of grupo) this.porId.set(m.idMuestra, marcador);
        }
        requestAnimationFrame(() => { this.mapa.invalidateSize(); this.centrar(); });
        const id = respuesta.consulta.municipio ?? respuesta.consulta.provincia ?? 'BO07';
        const temporizador = setTimeout(() => control.abort(), 15000);
        try {
            const r = await pedirJson(`${this.base}/territorios/${encodeURIComponent(id)}/limite`, control.signal);
            if (turno !== this.generacion) return;
            if (r.territorio?.identificador !== id || r.procedencia?.version !== this.version || r.limite?.type !== 'Feature') throw new Error('Límite incompatible');
            this.limite = L.geoJSON(r.limite, { interactive: false, style: { color: '#526b60', weight: 1.5, fill: false } }).addTo(this.mapa);
            this.limite.bringToBack(); this.centrar();
        } catch { if (turno === this.generacion) this.avisar('No se pudo cargar el contorno territorial. Los puntos y la lista siguen disponibles.'); }
        finally { clearTimeout(temporizador); }
    }
    centrar() {
        if (!this.mapa) return;
        const limite = this.limite?.getBounds();
        const puntos = window.L.featureGroup([...new Set(this.porId.values())]).getBounds();
        const bounds = limite?.isValid() ? limite : puntos;
        if (bounds.isValid()) this.mapa.fitBounds(bounds, { padding: [22, 22], maxZoom: 10, animate: false });
    }
    resaltar(id) {
        const elegido = this.porId.get(id);
        for (const punto of new Set(this.porId.values())) punto.setStyle({ weight: punto === elegido ? 4 : 2, color: punto === elegido ? '#092f26' : '#fff' });
    }
}
