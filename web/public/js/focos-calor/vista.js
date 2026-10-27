import { fechaBolivia, numero } from './contrato.js';
import { MapaFocos } from './mapa.js';
import { etiquetaDeteccion } from './representacion.js';

const texto = (id, valor) => { document.getElementById(id).textContent = valor; };
const nodo = (tipo, contenido, clase) => {
    const e = document.createElement(tipo);
    if (contenido !== undefined) e.textContent = contenido;
    if (clase) e.className = clase;
    return e;
};

export class VistaFocos {
    constructor() {
        this.nombres = new Map();
        this.mapa = new MapaFocos('mapa-focos', (id, origen) => this.detalle(id, origen), mensaje => {
            texto('error-mapa', mensaje ?? ''); document.getElementById('error-mapa').hidden = !mensaje;
        }, id => this.nombres.get(id));
        document.getElementById('lista-focos').addEventListener('click', e => {
            const boton = e.target.closest('button[data-foco]');
            if (boton) this.detalle(boton.dataset.foco, boton);
        });
        document.getElementById('centrar-territorio').addEventListener('click', () => this.mapa.centrar());
        document.getElementById('cerrar-detalle').addEventListener('click', () => {
            document.getElementById('detalle-foco').hidden = true;
            this.mapa.seleccionar(null);
            document.querySelectorAll('.fila-foco').forEach(b => b.setAttribute('aria-pressed', 'false'));
            if (this.origenDetalle?.isConnected) this.origenDetalle.focus();
        });
    }

    mostrar(estado) {
        const r = estado.actual?.respuesta;
        const cargando = estado.fase === 'cargando';
        const anterior = Boolean(r && (cargando || estado.fase === 'error' || estado.borrador));
        const resultados = document.getElementById('resultados-focos');
        resultados.hidden = !r;
        resultados.setAttribute('aria-busy', String(cargando));
        resultados.classList.toggle('resultados-anteriores', anterior);
        texto('etiqueta-vigencia', anterior ? 'RESULTADOS ANTERIORES · CONSERVADOS' : 'CONSULTA APLICADA');
        const aviso = document.getElementById('aviso-consulta');
        aviso.dataset.estado = cargando ? 'cargando' : anterior ? 'anterior' : estado.fase;
        let titulo = 'Listo para explorar';
        let mensaje = 'Revisa los filtros y pulsa Consultar focos. Los resultados se obtienen de la fuente externa.';
        if (r) {
            titulo = r.cobertura.estado === 'sin_cobertura' ? 'Sin cobertura para este período' : r.cobertura.estado === 'parcial' ? 'Resultados parciales' : 'Consulta lista';
            mensaje = 'Mapa y lista corresponden a la misma página. El resumen abarca todo el conjunto filtrado.';
            if (r.datos.length === 0) {
                titulo = r.paginacion.total > 0 ? 'Página sin registros' : 'Sin registros para estos filtros';
                mensaje = r.paginacion.total > 0 ? 'El conjunto sí contiene detecciones. Vuelve a una página anterior.' : 'La consulta no devolvió detecciones. Esto no demuestra ausencia de incendios ni de otros fenómenos.';
            }
            if (r.cobertura.estado === 'sin_cobertura') titulo = 'Sin cobertura para este período';
        }
        if (estado.borrador) { titulo = 'Filtros modificados'; mensaje = r ? 'Los resultados visibles conservan su territorio y período anteriores. Aplica los nuevos filtros o deshaz los cambios.' : 'Pulsa Consultar focos para aplicar la selección.'; }
        if (cargando) { titulo = 'Consultando la fuente…'; mensaje = r ? 'Se conservan los resultados anteriores, identificados abajo. Espera la nueva respuesta.' : 'Estamos consultando NASA FIRMS desde GeoCruz. Puede tardar unos segundos.'; }
        if (estado.fase === 'error') { titulo = estado.error?.codigo === 'CONJUNTO_CAMBIADO' ? 'El conjunto de consulta expiró' : 'No se pudo completar la consulta'; mensaje = `${estado.error?.message || 'Reintenta más tarde.'}${r ? ' Los resultados anteriores se conservan con sus fechas y filtros originales.' : ' No se sustituyeron por datos de demostración.'}`; }
        texto('titulo-estado', titulo); texto('mensaje-estado', mensaje);
        document.getElementById('reintentar').hidden = estado.fase !== 'error' || estado.error?.codigo === 'CONJUNTO_CAMBIADO' || !estado.pendiente;
        document.getElementById('reiniciar').hidden = estado.error?.codigo !== 'CONJUNTO_CAMBIADO';
        document.getElementById('cancelar-filtros').hidden = !estado.borrador;
        document.getElementById('consultar').disabled = cargando;
        document.getElementById('anterior').disabled = !r || estado.fase !== 'listo' || estado.borrador || r.paginacion.pagina <= 1;
        document.getElementById('siguiente').disabled = !r || estado.fase !== 'listo' || estado.borrador || r.paginacion.siguiente === null;
        if (r && this.ultimaRespuesta !== r) {
            // Tras la primera consulta, liberar el mapa sin perder los filtros editables.
            if (!this.ultimaRespuesta && matchMedia('(min-width: 768px)').matches) document.getElementById('controles-mapa').open = false;
            this.ultimaRespuesta = r;
            this.actual = estado.actual;
            this.pintarRespuesta(r);
            this.mapa.mostrar(r.datos, estado.actual.limite, estado.actual.errorMapa);
        }
    }

    pintarRespuesta(r) {
        const territorio = r.consulta.territorio;
        texto('titulo-resultados', this.nombres.get(territorio.identificador) ?? territorio.identificador);
        texto('periodo-aplicado', `${fechaBolivia(r.consulta.periodo.desde)} → ${fechaBolivia(r.consulta.periodo.hasta)} · Bolivia · final excluido`);
        const obt = r.consulta.obtencion;
        texto('tipo-obtencion', obt.tipo === 'cache' ? `Datos en caché · ${numero(Math.floor(obt.antiguedadSegundos / 60))} min de antigüedad al responder` : 'Datos recuperados de la fuente');
        texto('fecha-obtencion', `Recuperación: ${fechaBolivia(obt.fechaRecuperacion)} · Bolivia`);
        const indicador = codigo => r.resumen.indicadores.find(i => i.codigo === codigo);
        texto('total-focos', numero(r.resumen.totalRegistros));
        texto('confianza-alta', numero(indicador('focosConfianzaAlta')?.valor));
        texto('frp-maxima', numero(indicador('potenciaMaxima')?.valor, 'MW'));
        texto('frp-validos', `${numero(indicador('potenciaMaxima')?.registrosValidos)} FRP disponibles en el conjunto`);
        texto('total-faltantes', numero(r.resumen.registrosConFaltantes));
        const cobertura = { desconocida: 'Cobertura desconocida', parcial: 'Cobertura parcial', sin_cobertura: 'Sin cobertura comprobada', disponible: 'Datos disponibles con límites del producto' };
        texto('estado-cobertura', `${cobertura[r.cobertura.estado] ?? 'Cobertura no informada'}. ${r.cobertura.fundamento}`);
        texto('alcance-mapa', `Mapa y lista: ${numero(r.datos.length)} de ${numero(r.paginacion.total)} detecciones. Los puntos corresponden solo a la página actual.`);
        texto('conteo-pagina', `${numero(r.datos.length)} en esta página`);
        texto('pagina-actual', `Página ${r.paginacion.pagina} de ${Math.max(1, Math.ceil(r.paginacion.total / r.paginacion.tamanioPagina))}`);
        const lista = document.getElementById('lista-focos'); lista.replaceChildren();
        if (r.datos.length === 0) lista.append(nodo('p', r.paginacion.total > 0 ? 'Esta página no contiene registros. El resumen conserva el total del conjunto.' : 'Sin detecciones para esta consulta. Revisa el período y la cobertura publicada.', 'sin-resultados'));
        for (const foco of r.datos) {
            const boton = nodo('button', undefined, 'fila-foco'); boton.type = 'button'; boton.dataset.foco = foco.identificador;
            boton.setAttribute('aria-pressed', 'false'); boton.setAttribute('aria-label', `Ver detección: ${etiquetaDeteccion(foco, this.nombres.get(foco.territorio.municipio))}`);
            boton.append(nodo('strong', fechaBolivia(foco.fechas.observadoDesde)), nodo('span', numero(foco.atributos.potenciaRadiativa, 'MW'), 'frp'), nodo('span', `Confianza ${foco.atributos.confianza}`), nodo('span', `Ver detalle ↗`), nodo('span', this.nombres.get(foco.territorio.municipio) ?? (foco.territorio.motivoAsignacion ? 'Asignación municipal no determinada' : 'Santa Cruz'), 'municipio-foco'));
            lista.append(boton);
        }
        document.getElementById('detalle-foco').hidden = true;
        const procedencia = document.getElementById('procedencia-consulta'); procedencia.replaceChildren();
        for (const fuente of r.fuentes) {
            procedencia.append(nodo('p', `${fuente.proveedor} · ${fuente.producto} · versión ${fuente.version ?? 'no informada'} · ${fuente.naturaleza === 'observacion' ? 'Observación satelital' : fuente.naturaleza}. ${fuente.resolucion?.descripcion ?? ''}`));
        }
        procedencia.append(nodo('p', `Consulta del backend: ${fechaBolivia(r.consulta.fechaConsulta)} (Bolivia). Recuperación original: ${fechaBolivia(obt.fechaRecuperacion)}. Actualización del producto: ${fechaBolivia(obt.fechaActualizacion)}. Conjunto retenido hasta ${fechaBolivia(obt.expiraEn)}.`));
        procedencia.append(nodo('p', `Catálogo territorial: ${territorio.fuenteCatalogo}, ${territorio.versionCatalogo}. Geometrías de referencia de 2013; no constituyen deslinde legal. Ministerio de Desarrollo Rural y Tierras; OCHA FIS / HDX · CC BY 3.0 IGO. Adopción del catálogo pendiente de revisión.`));
        if (r.cobertura.periodoDisponible) procedencia.append(nodo('p', `Disponibilidad temporal del producto (UTC): ${r.cobertura.periodoDisponible.desde} → ${r.cobertura.periodoDisponible.hasta}. No demuestra observación completa de todo el territorio.`));
        r.advertencias.forEach(a => procedencia.append(nodo('p', a.mensaje)));
    }

    detalle(id, origen) {
        const r = this.actual?.respuesta;
        const f = r?.datos.find(foco => foco.identificador === id);
        if (!f) return;
        this.origenDetalle = origen;
        this.mapa.seleccionar(id);
        document.querySelectorAll('.fila-foco').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.foco === id)));
        const campos = document.getElementById('campos-detalle'); campos.replaceChildren();
        const coordenadas = f.geometria?.type === 'Point' ? `${f.geometria.coordinates[1]}, ${f.geometria.coordinates[0]} (latitud, longitud)` : 'No informadas';
        const valores = [
            ['Identificador', f.identificador], ['Adquisición · Bolivia', fechaBolivia(f.fechas.observadoDesde)], ['Adquisición · UTC', f.fechas.observadoDesde],
            ['Confianza de detección', f.atributos.confianza], ['Confianza original', f.atributos.confianzaOriginal ?? 'No informada'], ['Potencia radiativa · FRP', numero(f.atributos.potenciaRadiativa, 'MW')],
            ['Municipio', this.nombres.get(f.territorio.municipio) ?? 'No determinado'], ['Provincia', this.nombres.get(f.territorio.provincia) ?? 'No determinada'], ['Coordenadas · WGS84', coordenadas],
            ['Satélite / instrumento', `${f.atributos.satelite ?? 'No informado'} / ${f.atributos.instrumento ?? 'No informado'}`], ['Naturaleza', f.naturaleza === 'observacion' ? 'Observación satelital' : f.naturaleza], ['Fuente / producto', r.fuentes.map(s => `${s.proveedor} · ${s.producto}`).join('; ')],
            ['Versión del registro', f.atributos.versionOriginal ?? 'No informada'], ['Recuperación · Bolivia', fechaBolivia(r.consulta.obtencion.fechaRecuperacion)], ['Consulta · Bolivia', fechaBolivia(r.consulta.fechaConsulta)],
        ];
        valores.forEach(([nombre, valor]) => { const grupo = nodo('div'); grupo.append(nodo('dt', nombre), nodo('dd', valor)); campos.append(grupo); });
        texto('faltantes-detalle', f.faltantes.length ? `Faltantes: ${f.faltantes.map(x => `${x.campo}: ${x.motivo}`).join(' · ')}` : 'Sin faltantes declarados en los atributos del registro. Un foco térmico no confirma un incendio.');
        document.getElementById('detalle-foco').hidden = false;
        document.getElementById('titulo-detalle').focus({ preventScroll: true });
        document.getElementById('detalle-foco').scrollIntoView({ block: 'nearest', behavior: 'instant' });
    }
}
