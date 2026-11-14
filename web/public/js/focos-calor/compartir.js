import { fechaBolivia } from './contrato.js';

export function avisoDeteccion(foco, { municipio, provincia, fuentes = [] } = {}) {
    const coordenadas = foco?.geometria?.coordinates;
    if (foco?.geometria?.type !== 'Point' || !Array.isArray(coordenadas) || coordenadas.length !== 2) throw new Error('Ubicación no disponible.');
    const [lon, lat] = coordenadas;
    if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) throw new Error('Ubicación no disponible.');
    const mapa = new URL('https://www.google.com/maps/search/');
    mapa.searchParams.set('api', '1');
    mapa.searchParams.set('query', lat + ',' + lon);
    const lugar = [municipio, provincia].filter(Boolean).join(', ') || 'Lugar no identificado';
    const fuente = [...new Set(fuentes.map(f => f.proveedor).filter(Boolean))].join(', ') || 'No informada';
    return [
        'Detección de calor',
        'Lugar: ' + lugar,
        'Fecha: ' + fechaBolivia(foco.fechas?.observadoDesde) + ' (Bolivia, UTC-4)',
        'Coordenadas: ' + lat + ', ' + lon + ' (latitud, longitud)',
        'Fuente: ' + fuente,
        'Ubicación: ' + mapa.href,
        'Una detección térmica no confirma un incendio. Requiere verificación en la zona.',
    ].join('\n');
}

export class CompartirFoco {
    constructor(boton, estado, alternativa, portapapeles = globalThis.navigator?.clipboard) {
        Object.assign(this, { boton, estado, alternativa, portapapeles, version: 0 });
        boton.addEventListener('click', () => this.copiar());
        this.limpiar();
    }

    limpiar() {
        ++this.version;
        this.texto = null;
        this.boton.disabled = true;
        this.estado.textContent = '';
        this.alternativa.hidden = true;
        this.alternativa.value = '';
    }

    seleccionar(foco, contexto) {
        this.limpiar();
        try { this.texto = avisoDeteccion(foco, contexto); this.boton.disabled = false; }
        catch { this.estado.textContent = 'Esta detección no tiene una ubicación válida para compartir.'; }
    }

    async copiar() {
        if (!this.texto) return;
        const version = this.version;
        const texto = this.texto;
        try {
            if (!this.portapapeles?.writeText) throw new Error('Portapapeles no disponible');
            await this.portapapeles.writeText(texto);
            if (version === this.version) this.estado.textContent = 'Ubicación y fecha copiadas. Ya puedes pegarlas en tu mensaje.';
        } catch {
            if (version !== this.version) return;
            this.alternativa.value = texto;
            this.alternativa.hidden = false;
            this.estado.textContent = 'Selecciona y copia este texto para compartirlo.';
            this.alternativa.focus();
            this.alternativa.select();
        }
    }
}
