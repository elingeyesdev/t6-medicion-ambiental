import { fechaBolivia, numero } from './contrato.js';
import { MapaFocos } from './mapa.js';
import { etiquetaDeteccion } from './representacion.js';
import { crearContenidoFoco } from './elementos.js';

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
        this.resultadosOcultos = false;
        this.mapa = new MapaFocos('mapa-focos', (id, origen) => this.detalle(id, origen), mensaje => {
            texto('error-mapa', mensaje ?? ''); document.getElementById('error-mapa').hidden = !mensaje;
        }, id => this.nombres.get(id));
        document.getElementById('lista-focos').addEventListener('click', e => {
            const boton = e.target.closest('button[data-foco]');
            if (boton) this.detalle(boton.dataset.foco, boton);
        });
        document.getElementById('centrar-territorio').addEventListener('click', () => this.mapa.centrar());
        document.getElementById('cerrar-resultados').addEventListener('click', () => {
            this.mostrarPanelResultados(false);
            document.getElementById('mostrar-resultados').focus({ preventScroll: true });
        });
        document.getElementById('mostrar-resultados').addEventListener('click', () => this.mostrarPanelResultados(this.resultadosOcultos));
        document.getElementById('cerrar-detalle').addEventListener('click', () => {
            document.getElementById('detalle-foco').hidden = true;
            this.mapa.seleccionar(null);
            document.querySelectorAll('.fila-foco').forEach(b => b.setAttribute('aria-pressed', 'false'));
            if (this.origenDetalle?.isConnected) this.origenDetalle.focus();
        });
    }

    mostrarPanelResultados(mostrar) {
        this.resultadosOcultos = !mostrar;
        const disponible = Boolean(this.actual?.respuesta);
        document.getElementById('resultados-focos').hidden = !disponible || !mostrar;
        const boton = document.getElementById('mostrar-resultados');
        boton.hidden = !disponible;
        boton.setAttribute('aria-expanded', String(disponible && mostrar));
        boton.textContent = mostrar ? 'Ocultar resultados' : `Ver resultados (${numero(this.actual?.respuesta.resumen.totalRegistros)})`;
    }

    mostrar(estado) {
        const r = estado.actual?.respuesta;
        const cargando = estado.fase === 'cargando';
        const anterior = Boolean(r && (cargando || estado.fase === 'error' || estado.borrador));
        const resultados = document.getElementById('resultados-focos');
        if (r && this.ultimaRespuesta !== r) this.resultadosOcultos = false;
        this.actual = estado.actual;
        this.mostrarPanelResultados(!this.resultadosOcultos);
        resultados.setAttribute('aria-busy', String(cargando));
        resultados.classList.toggle('resultados-anteriores', anterior);
        texto('etiqueta-vigencia', anterior ? 'Resultados anteriores' : 'Resultados');
        const aviso = document.getElementById('aviso-consulta');
        aviso.dataset.estado = cargando ? 'cargando' : anterior ? 'anterior' : estado.fase;
        let titulo = 'Elige los filtros';
        let mensaje = 'Elige territorio y fechas.';
        if (r) {
            titulo = r.cobertura.estado === 'sin_cobertura' ? 'Sin cobertura para este período' : r.cobertura.estado === 'parcial' ? 'Resultados parciales' : 'Consulta lista';
            mensaje = 'Resultados del período seleccionado.';
            if (r.datos.length === 0) {
                titulo = r.paginacion.total > 0 ? 'Página sin registros' : 'Sin registros para estos filtros';
                mensaje = r.paginacion.total > 0 ? 'Vuelve a la página anterior.' : 'Sin detecciones publicadas. No descarta incendios.';
            }
            if (r.cobertura.estado === 'sin_cobertura') titulo = 'Sin cobertura para este período';
        }
        if (estado.borrador) { titulo = 'Filtros modificados'; mensaje = r ? 'Aplica los cambios. El mapa conserva la consulta anterior.' : 'Pulsa Consultar focos.'; }
        if (cargando) { titulo = 'Cargando…'; mensaje = r ? 'El mapa conserva la consulta anterior.' : 'Espera unos segundos.'; }
        if (estado.fase === 'error') { titulo = estado.error?.codigo === 'CONJUNTO_CAMBIADO' ? 'La consulta venció' : 'No se pudo consultar'; mensaje = `${estado.error?.message || 'Reintenta más tarde.'}${r ? ' Se conserva la consulta anterior.' : ''}`; }
        texto('titulo-estado', titulo); texto('mensaje-estado', mensaje);
        // La confirmación normal se anuncia sin añadir otra tarjeta al mapa.
        aviso.classList.toggle('solo-lector', titulo === 'Consulta lista' && !anterior && !cargando && estado.fase !== 'error');
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
        texto('periodo-aplicado', `${fechaBolivia(r.consulta.periodo.desde)} → ${fechaBolivia(r.consulta.periodo.hasta)} · Bolivia`);
        const indicador = codigo => r.resumen.indicadores.find(i => i.codigo === codigo);
        texto('total-focos', numero(r.resumen.totalRegistros));
        texto('confianza-alta', numero(indicador('focosConfianzaAlta')?.valor));
        const cobertura = { desconocida: 'El mapa puede no incluir todas las detecciones.', parcial: 'Solo hay datos para parte de la consulta.', sin_cobertura: 'No hay datos disponibles para estas fechas.', disponible: 'Datos disponibles para la consulta.' };
        texto('estado-cobertura', cobertura[r.cobertura.estado] ?? cobertura.desconocida);
        texto('alcance-mapa', `Mapa: ${numero(r.datos.length)} de ${numero(r.paginacion.total)} detecciones · página ${r.paginacion.pagina}`);
        texto('conteo-pagina', `${numero(r.datos.length)} en esta página`);
        texto('pagina-actual', `Página ${r.paginacion.pagina} de ${Math.max(1, Math.ceil(r.paginacion.total / r.paginacion.tamanioPagina))}`);
        const lista = document.getElementById('lista-focos'); lista.replaceChildren();
        if (r.datos.length === 0) lista.append(nodo('p', r.paginacion.total > 0 ? 'Esta página no contiene registros. El resumen conserva el total del conjunto.' : 'Sin detecciones para esta consulta. Revisa el período y la cobertura publicada.', 'sin-resultados'));
        for (const foco of r.datos) {
            const boton = nodo('button', undefined, 'fila-foco'); boton.type = 'button'; boton.dataset.foco = foco.identificador;
            boton.setAttribute('aria-pressed', 'false'); boton.setAttribute('aria-label', `Ver detección: ${etiquetaDeteccion(foco, this.nombres.get(foco.territorio.municipio))}`);
            boton.append(...crearContenidoFoco(foco, this.nombres.get(foco.territorio.municipio)));
            lista.append(boton);
        }
        document.getElementById('detalle-foco').hidden = true;
        const procedencia = document.getElementById('procedencia-consulta'); procedencia.replaceChildren();
        procedencia.append(nodo('p', `Fuente: ${[...new Set(r.fuentes.map(f => f.proveedor))].join(' · ')}.`));
        procedencia.append(nodo('p', 'Límites de referencia: OCHA/HDX · CC BY 3.0 IGO.'));
        if (r.resumen.registrosConFaltantes > 0) procedencia.append(nodo('p', 'Algunos puntos tienen información incompleta.'));
    }

    detalle(id, origen) {
        const r = this.actual?.respuesta;
        const f = r?.datos.find(foco => foco.identificador === id);
        if (!f) return;
        this.mostrarPanelResultados(true);
        this.origenDetalle = origen;
        document.querySelectorAll('.fila-foco').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.foco === id)));
        const campos = document.getElementById('campos-detalle'); campos.replaceChildren();
        const extras = document.getElementById('campos-detalle-extra'); extras.replaceChildren();
        const coordenadas = f.geometria?.type === 'Point' ? `${f.geometria.coordinates[1]}, ${f.geometria.coordinates[0]} (latitud, longitud)` : 'No informadas';
        const valores = [
            ['Fecha y hora (Bolivia)', fechaBolivia(f.fechas.observadoDesde)], ['Nivel de confianza', f.atributos.confianza],
            ['Municipio', this.nombres.get(f.territorio.municipio) ?? 'No determinado'], ['Provincia', this.nombres.get(f.territorio.provincia) ?? 'No determinada'],
            ['Ubicación', coordenadas], ['Fuente', [...new Set(r.fuentes.map(s => s.proveedor))].join(' · ')],
        ];
        const principales = new Set(['Municipio', 'Provincia', 'Fecha y hora (Bolivia)', 'Nivel de confianza']);
        valores.forEach(([nombre, valor]) => { const grupo = nodo('div'); grupo.append(nodo('dt', nombre), nodo('dd', valor)); (principales.has(nombre) ? campos : extras).append(grupo); });
        texto('faltantes-detalle', f.faltantes.length ? 'Este punto tiene información incompleta.' : '');
        document.getElementById('detalle-foco').hidden = false;
        texto('titulo-detalle', `Detección ${r.datos.indexOf(f) + 1} de ${r.datos.length}`);
        this.mapa.seleccionar(id);
        document.getElementById('titulo-detalle').focus({ preventScroll: true });
        if (matchMedia('(max-width: 767.98px)').matches) document.getElementById(this.mapa.contenedor).scrollIntoView({ block: 'center', behavior: 'instant' });
    }
}
