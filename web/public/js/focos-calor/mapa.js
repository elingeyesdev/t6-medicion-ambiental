import { agruparProximos, etiquetaDeteccion } from './representacion.js';

const colores = { alta: '#bd671d', nominal: '#25856b', baja: '#ad9a53', desconocida: '#788084' };

/** Leaflet representa la página actual; no recalcula pertenencia ni métricas. */
export class MapaFocos {
    constructor(contenedor, alSeleccionar, avisar, nombreMunicipio = () => undefined) {
        this.contenedor = contenedor;
        this.alSeleccionar = alSeleccionar;
        this.avisar = avisar;
        this.nombreMunicipio = nombreMunicipio;
        this.marcadores = new Map();
        this.datos = [];
        this.idSeleccionado = null;
        this.panelGrupo = document.getElementById('grupo-focos');
        this.listaGrupo = document.getElementById('lista-grupo');
        document.getElementById('cerrar-grupo').addEventListener('click', () => {
            this.panelGrupo.hidden = true;
            document.getElementById(this.contenedor).focus({ preventScroll: true });
        });
    }

    iniciar() {
        if (this.mapa) return true;
        if (!window.L) { this.avisar('No se pudo iniciar el mapa. Puedes consultar todas las detecciones de esta página en el listado.'); return false; }
        const L = window.L;
        // Sin desvanecimiento: retirar una etiqueta debe ser inmediato al cambiar de selección.
        this.mapa = L.map(this.contenedor, { scrollWheelZoom: false, fadeAnimation: false, zoomAnimation: !matchMedia('(prefers-reduced-motion: reduce)').matches }).setView([-17.5, -61.5], 6);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).on('tileerror', () => this.avisar('Algunas imágenes del mapa base no pudieron cargarse. Los límites, puntos y el listado siguen disponibles.')).addTo(this.mapa);
        this.mapa.createPane('seleccionFocos').style.zIndex = '625';
        this.puntos = L.layerGroup().addTo(this.mapa);
        this.mapa.on('zoomend', () => this.reagrupar());
        // El menú puede contraerse sin recargar ni deformar el mapa.
        this.observador = new ResizeObserver(() => this.mapa.invalidateSize());
        this.observador.observe(document.getElementById(this.contenedor));
        return true;
    }

    etiqueta(foco) { return etiquetaDeteccion(foco, this.nombreMunicipio(foco.territorio.municipio)); }

    mostrar(datos, limite, errorMapa) {
        this.avisar(errorMapa);
        this.seleccionar(null);
        this.panelGrupo.hidden = true;
        this.datos = datos.filter(f => f.geometria?.type === 'Point' && f.geometria.coordinates?.length === 2 && f.geometria.coordinates.every(Number.isFinite));
        try {
            if (!this.iniciar()) return;
            const L = window.L;
            this.puntos.clearLayers(); this.marcadores.clear();
            if (this.limite) this.limite.remove();
            this.limite = limite ? L.geoJSON(limite, { interactive: false, style: { color: '#49705b', weight: 1.6, fillColor: '#c6d9bc', fillOpacity: 0.15 } }).addTo(this.mapa) : null;
            for (const foco of this.datos) {
                const [x, y] = foco.geometria.coordinates;
                const punto = L.circleMarker([y, x], { radius: 6, fillColor: colores[foco.atributos.confianza] ?? colores.desconocida, color: '#fff', weight: 1.2, fillOpacity: 0.9, className: 'marcador-foco' });
                punto.on('click', () => this.alSeleccionar(foco.identificador, punto.getElement()));
                // El SVG se vuelve a crear al reagrupar; el teclado se conecta en cada alta.
                punto.on('add', () => {
                    const elemento = punto.getElement();
                    if (!elemento) return;
                    elemento.setAttribute('tabindex', '0'); elemento.setAttribute('role', 'button'); elemento.setAttribute('aria-label', this.etiqueta(foco));
                    elemento.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.alSeleccionar(foco.identificador, elemento); } });
                });
                this.marcadores.set(foco.identificador, punto);
            }
            this.reagrupar();
            requestAnimationFrame(() => { this.mapa.invalidateSize(); this.centrar(); });
        } catch { this.avisar('El mapa no pudo dibujarse. El listado y los detalles de la consulta siguen disponibles.'); }
    }

    reagrupar() {
        if (!this.mapa || !this.puntos) return;
        const L = window.L;
        this.puntos.clearLayers();
        const entradas = this.datos.map(foco => ({ foco, punto: this.mapa.latLngToLayerPoint([foco.geometria.coordinates[1], foco.geometria.coordinates[0]]) }));
        for (const grupo of agruparProximos(entradas)) {
            if (grupo.length === 1) {
                this.marcadores.get(grupo[0].foco.identificador).addTo(this.puntos);
                continue;
            }
            const miembros = grupo.map(e => e.foco);
            const [x, y] = miembros[0].geometria.coordinates;
            const contenido = document.createElement('span'); contenido.textContent = String(miembros.length); contenido.setAttribute('aria-hidden', 'true');
            const titulo = `${miembros.length} detecciones próximas de esta página. Acercar y ver registros`;
            // El símbolo de grupo se ancla en una coordenada existente, nunca en un punto inventado.
            const agrupacion = L.marker([y, x], { icon: L.divIcon({ html: contenido, className: 'agrupacion-focos', iconSize: [34, 34], iconAnchor: [17, 17] }), title: titulo, keyboard: true }).addTo(this.puntos);
            agrupacion.getElement()?.setAttribute('aria-label', titulo);
            agrupacion.getElement()?.addEventListener('keydown', e => { if (e.key === ' ') { e.preventDefault(); this.mostrarGrupo(miembros); } });
            agrupacion.on('click', () => this.mostrarGrupo(miembros));
        }
        this.actualizarSeleccion();
    }

    mostrarGrupo(miembros) {
        this.listaGrupo.replaceChildren();
        document.getElementById('titulo-grupo').textContent = `${miembros.length} detecciones del grupo seleccionado`;
        for (const foco of miembros) {
            const boton = document.createElement('button'); boton.type = 'button'; boton.className = 'opcion-grupo';
            boton.textContent = this.etiqueta(foco); boton.dataset.focoGrupo = foco.identificador;
            boton.setAttribute('aria-pressed', String(foco.identificador === this.idSeleccionado));
            boton.addEventListener('click', () => this.alSeleccionar(foco.identificador, boton));
            this.listaGrupo.append(boton);
        }
        this.panelGrupo.hidden = false;
        // Coordenadas idénticas también se pueden consultar sin separarlas artificialmente.
        const posiciones = miembros.map(f => [f.geometria.coordinates[1], f.geometria.coordinates[0]]);
        this.mapa.fitBounds(window.L.latLngBounds(posiciones), { padding: [35, 35], maxZoom: 18, animate: false });
        document.getElementById('titulo-grupo').focus({ preventScroll: true });
    }

    centrar() {
        if (this.limite) {
            const ancho = document.getElementById(this.contenedor).clientWidth;
            const escritorio = matchMedia('(min-width: 768px)').matches;
            const controles = document.getElementById('controles-mapa');
            const resultados = document.getElementById('resultados-focos');
            const izquierda = escritorio && controles?.open ? controles.offsetWidth + 35 : 18;
            const derecha = escritorio && !resultados?.hidden ? resultados.offsetWidth + 32 : 18;
            // Con poco espacio se muestra el contorno completo y se pueden plegar los filtros.
            const cabenPaneles = ancho - izquierda - derecha > 200;
            const arriba = escritorio && !controles?.open ? (document.querySelector('.enviro-controles')?.offsetHeight ?? 35) + 40 : 65;
            this.mapa?.fitBounds(this.limite.getBounds(), { paddingTopLeft: [cabenPaneles ? izquierda : 18, arriba], paddingBottomRight: [cabenPaneles ? derecha : 18, 100], maxZoom: 11, animate: false });
        }
        else if (this.marcadores.size) this.mapa?.fitBounds(window.L.featureGroup([...this.marcadores.values()]).getBounds(), { padding: [25, 25], maxZoom: 10, animate: false });
    }

    seleccionar(id) {
        this.idSeleccionado = id;
        this.actualizarSeleccion();
        this.listaGrupo.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.focoGrupo === id)));
    }

    actualizarSeleccion() {
        // Una sola etiqueta administrada por el visor; cambiar o cerrar elimina la anterior.
        this.etiquetaActual?.remove(); this.etiquetaActual = null;
        this.anilloActual?.remove(); this.anilloActual = null;
        const foco = this.datos.find(f => f.identificador === this.idSeleccionado);
        if (!this.mapa || !foco) return;
        const [x, y] = foco.geometria.coordinates;
        const posicion = [y, x];
        // Dejar espacio a la etiqueta sin cambiar la coordenada de la detección.
        this.mapa.panInside(posicion, { paddingTopLeft: [135, 110], paddingBottomRight: [135, 30], animate: false });
        this.anilloActual = window.L.circleMarker(posicion, { radius: 15, color: '#173d2e', weight: 3, fill: false, interactive: false, pane: 'seleccionFocos' }).addTo(this.mapa);
        const contenido = document.createElement('span'); contenido.textContent = this.etiqueta(foco);
        this.etiquetaActual = window.L.tooltip({ direction: 'top', offset: [0, -16], className: 'etiqueta-deteccion' }).setLatLng(posicion).setContent(contenido).addTo(this.mapa);
    }
}
