import {esViento, muestraVector} from './raster.js';

// Pantalla norte-arriba (Leaflet/Web Mercator). U va al este, V al norte.
// La velocidad en pantalla es ilustrativa, no un desplazamiento físico/horario.
export function pasoViento(vector, segundos) {
    if (!vector || ![vector.u, vector.v, segundos].every(Number.isFinite) || segundos <= 0) return null;
    const rapidez = Math.hypot(vector.u, vector.v);
    if (rapidez < 1) return null;
    const distancia = Math.min(60, 8 + rapidez * .6) * Math.min(segundos, .05);
    return {x: vector.u / rapidez * distancia, y: -vector.v / rapidez * distancia};
}

export function tramoConViento(vectorEn, x, y, destinoX, destinoY) {
    const pasos = Math.max(1, Math.ceil(Math.hypot(destinoX - x, destinoY - y) / 2));
    for (let i = 0; i <= pasos; i++) {
        const t = i / pasos;
        if (!pasoViento(vectorEn(x + (destinoX - x) * t, y + (destinoY - y) * t), .01)) return false;
    }
    return true;
}

// Un único bucle por capa, cancelable incluso desde el propio dibujo.
export function crearAnimador({dibujar, solicitar = requestAnimationFrame, cancelar = cancelAnimationFrame}) {
    let activo = false, pendiente = null, anterior = null;
    function cuadro(tiempo) {
        pendiente = null;
        if (!activo) return;
        const transcurrido = anterior === null ? 0 : tiempo - anterior;
        if (anterior === null || transcurrido >= 1000 / 30) {
            anterior = tiempo;
            dibujar(Math.min(Math.max(transcurrido / 1000, 0), .05));
        }
        if (activo) pendiente = solicitar(cuadro);
    }
    return {
        iniciar() { if (!activo) { activo = true; anterior = null; pendiente = solicitar(cuadro); } },
        parar() { activo = false; if (pendiente !== null) cancelar(pendiente); pendiente = anterior = null; },
    };
}

export function crearParticulasViento(L, cuadro, pixeles, pane, opacidad) {
    if (!esViento(cuadro)) throw new Error('Esta capa no contiene dirección del viento.');
    const Capa = L.Layer.extend({
        options: {pane},
        onAdd(mapa) {
            this.mapa = mapa;
            this.canvas = document.createElement('canvas');
            this.canvas.className = 'capas-particulas';
            this.canvas.setAttribute('aria-hidden', 'true');
            this.getPane().append(this.canvas);
            this.ctx = this.canvas.getContext('2d');
            this.canvas.style.opacity = String(opacidad);
            this.motor = crearAnimador({dibujar: dt => this.dibujar(dt)});
            this.mover = () => { this.moviendo = true; this.pausar(); };
            this.reponer = () => { this.moviendo = false; this.preparar(); };
            this.visibilidad = () => { if (document.hidden) this.pausar(); else if (!this.moviendo) this.preparar(); };
            mapa.on('movestart zoomstart', this.mover);
            mapa.on('moveend zoomend resize', this.reponer);
            document.addEventListener('visibilitychange', this.visibilidad);
            this.preparar();
        },
        onRemove(mapa) {
            this.pausar();
            mapa.off('movestart zoomstart', this.mover);
            mapa.off('moveend zoomend resize', this.reponer);
            document.removeEventListener('visibilitychange', this.visibilidad);
            this.canvas.remove();
            this.particulas = [];
            this.mapa = this.ctx = this.canvas = null;
        },
        setOpacity(valor) { if (this.canvas) this.canvas.style.opacity = String(valor); return this; },
        pausar() {
            this.motor.parar();
            this.ctx?.clearRect(0, 0, this.ancho ?? 0, this.alto ?? 0);
        },
        vectorEn(x, y) {
            if (x < 0 || y < 0 || x > this.ancho || y > this.alto) return null;
            const punto = this.mapa.containerPointToLatLng([x, y]);
            return muestraVector(cuadro, pixeles, punto.lat, punto.lng);
        },
        preparar() {
            this.pausar();
            if (document.hidden || !this.ctx || !this.mapa || this.moviendo) return;
            const tam = this.mapa.getSize(); this.ancho = tam.x; this.alto = tam.y;
            if (tam.x <= 0 || tam.y <= 0) return;
            // Limitar memoria y trabajo también en pantallas de alta densidad.
            const ratio = Math.min(window.devicePixelRatio || 1, 1.5, 2048 / tam.x, 2048 / tam.y);
            this.canvas.width = Math.max(1, Math.round(tam.x * ratio));
            this.canvas.height = Math.max(1, Math.round(tam.y * ratio));
            this.canvas.style.width = tam.x + 'px'; this.canvas.style.height = tam.y + 'px';
            L.DomUtil.setPosition(this.canvas, this.mapa.containerPointToLayerPoint([0, 0]));
            this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            this.particulas = [];
            const paso = Math.max(46, Math.sqrt(tam.x * tam.y / 300));
            for (let y = paso / 2; y < tam.y; y += paso) {
                for (let x = paso / 2; x < tam.x; x += paso) {
                    const px = x + (Math.random() - .5) * paso * .7, py = y + (Math.random() - .5) * paso * .7;
                    if (pasoViento(this.vectorEn(px, py), .01)) this.particulas.push(this.nacer(px, py, true));
                }
            }
            if (this.particulas.length) this.motor.iniciar();
        },
        nacer(x, y, repartir = false) {
            const duracion = 2 + Math.random() * 2;
            return {x, y, origenX: x, origenY: y, duracion, edad: repartir ? Math.random() * duracion : 0};
        },
        dibujar(dt) {
            if (dt <= 0) return;
            const ctx = this.ctx;
            ctx.clearRect(0, 0, this.ancho, this.alto);
            ctx.lineCap = 'round';
            for (let i = 0; i < this.particulas.length; i++) {
                const p = this.particulas[i], paso = pasoViento(this.vectorEn(p.x, p.y), dt);
                if (!paso || p.edad >= p.duracion || !tramoConViento((x, y) => this.vectorEn(x, y), p.x, p.y, p.x + paso.x, p.y + paso.y)) {
                    this.particulas[i] = this.nacer(p.origenX, p.origenY);
                    continue;
                }
                p.x += paso.x; p.y += paso.y; p.edad += dt;
                const norma = Math.hypot(paso.x, paso.y);
                if (!norma) continue;
                const largo = 7 + Math.min(5, norma * 3);
                const colaX = p.x - paso.x / norma * largo, colaY = p.y - paso.y / norma * largo;
                // No extender la cola por encima de un hueco de datos.
                if (!tramoConViento((x, y) => this.vectorEn(x, y), p.x, p.y, colaX, colaY)) continue;
                ctx.globalAlpha = .3 + .7 * Math.sin(Math.PI * Math.min(1, p.edad / p.duracion));
                ctx.beginPath(); ctx.moveTo(colaX, colaY); ctx.lineTo(p.x, p.y);
                ctx.strokeStyle = '#173849'; ctx.lineWidth = 3; ctx.stroke();
                ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();
            }
            ctx.globalAlpha = 1;
        },
    });
    return new Capa();
}
