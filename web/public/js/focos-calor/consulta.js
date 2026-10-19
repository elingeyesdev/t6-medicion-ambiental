import { solicitarJson, comprobarRespuesta, ErrorApi } from './contrato.js';

/** Conserva una respuesta aplicada; cada petición tiene generación y cancelación propias. */
export class ConsultaFocosWeb {
    constructor(baseApi, avisar, pedir = solicitarJson) {
        this.baseApi = baseApi;
        this.avisar = avisar;
        this.pedir = pedir;
        this.estado = { fase: 'inicial', actual: null, error: null, borrador: false, pendiente: null };
        this.generacion = 0;
    }

    emitir(cambios) { this.estado = { ...this.estado, ...cambios }; this.avisar(this.estado); }

    editar() {
        this.generacion++;
        this.control?.abort();
        this.emitir({ borrador: true, fase: this.estado.actual ? 'anterior' : 'inicial', error: null, pendiente: null });
    }

    restaurar() { this.emitir({ borrador: false, fase: this.estado.actual ? 'listo' : 'inicial', error: null }); }

    async consultar(filtros, pagina = 1, version = null) {
        const turno = ++this.generacion;
        this.control?.abort();
        const control = new AbortController();
        this.control = control;
        const pendiente = { filtros: { ...filtros }, pagina, version };
        this.emitir({ fase: 'cargando', error: null, borrador: false, pendiente });
        const temporizador = setTimeout(() => control.abort(new Error('tiempo_agotado')), 70000);
        try {
            const p = new URLSearchParams({ ...filtros, pagina: String(pagina), tamanioPagina: '25' });
            if (version) p.set('versionConjunto', version);
            const respuesta = comprobarRespuesta(await this.pedir(`${this.baseApi}/focos-calor?${p}`, control.signal), pagina);
            if (turno !== this.generacion) return;
            const anterior = this.estado.actual;
            if (version && (respuesta.paginacion.versionConjunto !== version || (anterior && JSON.stringify(anterior.respuesta.resumen) !== JSON.stringify(respuesta.resumen)))) {
                throw new ErrorApi('El conjunto cambió entre páginas. Reinicia la consulta.', 'CONJUNTO_CAMBIADO', 409);
            }
            const id = respuesta.consulta.territorio.identificador;
            let limite = anterior?.respuesta.consulta.territorio.identificador === id ? anterior.limite : null;
            let errorMapa = null;
            if (!limite) {
                try {
                    const recurso = await this.pedir(`${this.baseApi}/territorios/${encodeURIComponent(id)}/limite`, control.signal);
                    if (recurso.territorio?.identificador !== id || recurso.procedencia?.version !== respuesta.consulta.territorio.versionCatalogo || recurso.limite?.type !== 'Feature') throw new Error('Límite incompatible');
                    limite = recurso.limite;
                } catch { errorMapa = 'No se pudo cargar el límite territorial. Las detecciones y el listado siguen disponibles.'; }
            }
            if (turno !== this.generacion) return;
            this.emitir({ fase: 'listo', error: null, pendiente: null, actual: { respuesta, filtros: { ...filtros }, limite, errorMapa } });
        } catch (error) {
            if (turno !== this.generacion) return;
            const seguro = control.signal.aborted ? new ErrorApi('La consulta tardó demasiado. Reintenta sin cambiar los datos anteriores.', 'TIEMPO_AGOTADO') : error;
            this.emitir({ fase: 'error', error: seguro });
        } finally { clearTimeout(temporizador); }
    }

    paginar(pagina) {
        const actual = this.estado.actual;
        if (!actual || this.estado.fase !== 'listo' || this.estado.borrador) return;
        return this.consultar(actual.filtros, pagina, actual.respuesta.paginacion.versionConjunto);
    }

    reintentar() {
        const p = this.estado.pendiente;
        if (p) return this.consultar(p.filtros, p.pagina, p.version);
    }

    reiniciar() {
        const filtros = this.estado.pendiente?.filtros ?? this.estado.actual?.filtros;
        if (filtros) return this.consultar(filtros);
    }
}
