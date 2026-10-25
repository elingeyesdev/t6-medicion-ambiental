import { comprobarRespuesta, pedirJson, ErrorConsulta } from './contrato.js';

/** Una sola consulta aplicada. Las respuestas atrasadas nunca sustituyen la última selección. */
export class ConsultaSequiasWeb {
    constructor(base, avisar, pedir = pedirJson) {
        this.base = base; this.avisar = avisar; this.pedir = pedir; this.generacion = 0;
        this.estado = { fase: 'inicial', actual: null, pendiente: null, error: null, borrador: false };
    }
    emitir(cambios) { this.estado = { ...this.estado, ...cambios }; this.avisar(this.estado); }
    limpiar() {
        ++this.generacion; this.control?.abort();
        this.emitir({ fase: 'inicial', actual: null, pendiente: null, error: null, borrador: false });
    }
    editar() {
        ++this.generacion; this.control?.abort();
        this.emitir({ fase: this.estado.actual ? 'anterior' : 'inicial', borrador: true, pendiente: null, error: null });
    }
    restaurar() { this.emitir({ fase: 'listo', borrador: false, error: null, pendiente: null }); this.verificarVigencia(); }
    verificarVigencia() {
        if (this.estado.actual && Date.parse(this.estado.actual.respuesta.consulta.obtencion.vigenteHasta) <= Date.now()) {
            this.emitir({ fase: 'anterior', error: new ErrorConsulta('La publicación visible ha vencido. Se conserva solo como resultado anterior; reintenta después de renovarla.', 'PERIODO_NO_DISPONIBLE') });
        }
    }
    async consultar(filtros) {
        const turno = ++this.generacion;
        this.control?.abort(); const control = new AbortController(); this.control = control;
        this.emitir({ fase: 'cargando', pendiente: { ...filtros }, error: null, borrador: false });
        const tiempo = setTimeout(() => control.abort(), 30000);
        try {
            const respuesta = comprobarRespuesta(await this.pedir(`${this.base}/sequias?${new URLSearchParams(filtros)}`, control.signal), filtros);
            if (turno !== this.generacion) return;
            this.emitir({ fase: 'listo', actual: { filtros: { ...filtros }, respuesta }, pendiente: null });
        } catch (error) {
            if (turno !== this.generacion) return;
            this.emitir({ fase: 'error', error: control.signal.aborted ? new ErrorConsulta('La consulta tardó demasiado. Puedes reintentar.', 'TIEMPO_AGOTADO') : error });
        } finally { clearTimeout(tiempo); }
    }
    reintentar() { const f = this.estado.pendiente ?? this.estado.actual?.filtros; if (f) return this.consultar(f); }
}
