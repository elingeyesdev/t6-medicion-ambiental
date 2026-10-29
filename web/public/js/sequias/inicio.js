import { leerEnlace, validarFiltros, ErrorConsulta } from './contrato.js';
import { ConsultaSequiasWeb } from './consulta.js';
import { MapaSequias } from './mapa.js';
import { VistaSequias } from './vista.js';

const $ = id => document.getElementById(id);
const catalogos = JSON.parse($('sq-catalogos').textContent);
const formulario = $('sq-formulario');
let control, renderizado = null, seleccionPendiente = null, origenDetalle = null, vigencia;
const mapa = new MapaSequias($('visor-sequias').dataset.api, catalogos.versionTerritorial, seleccionar, mensaje => { $('sq-error-mapa').hidden = !mensaje; $('sq-error-mapa').textContent = mensaje ?? ''; });
const vista = new VistaSequias(catalogos, seleccionar);

function opciones(select, entradas, titulo, elegido = '') {
    select.replaceChildren(new Option(titulo, ''));
    for (const [id, nombre] of entradas) select.add(new Option(nombre, id));
    select.value = [...select.options].some(o => o.value === elegido) ? elegido : '';
}
function actualizarMunicipios(elegido = $('sq-municipio').value) {
    const provincia = $('sq-provincia').value;
    opciones($('sq-municipio'), catalogos.territorios.filter(t => t.tipo === 'municipio' && (!provincia || t.padre === provincia)).map(t => [t.identificador, t.nombre]), 'Todos los municipios', elegido);
}
function actualizarMuestras(elegido = $('sq-muestra').value) {
    const provincia = $('sq-provincia').value, municipio = $('sq-municipio').value;
    opciones($('sq-muestra'), catalogos.muestras.filter(m => (!provincia || m.provincia === provincia) && (!municipio || m.municipio === municipio)).map(m => [m.identificador, vista.etiquetaMuestra(m.identificador)]), 'Todos los puntos del territorio', elegido);
}
function aplicarFormulario(f) {
    const muestra = catalogos.muestras.find(m => m.identificador === f.muestra);
    const municipio = catalogos.territorios.find(t => t.identificador === (f.municipio ?? muestra?.municipio));
    $('sq-provincia').value = f.provincia ?? municipio?.padre ?? '';
    actualizarMunicipios(f.municipio ?? muestra?.municipio ?? ''); actualizarMuestras(f.muestra ?? '');
    $('sq-mes').value = f.mesFinal;
}
function actualizarEnlace(filtros, seleccion = null, reemplazar = false) {
    const u = new URL(location.href); u.search = new URLSearchParams(filtros).toString(); u.hash = '';
    if (seleccion) u.searchParams.set('seleccion', seleccion);
    history[reemplazar ? 'replaceState' : 'pushState'](null, '', u);
}
function seleccionar(id, origen) {
    if (!vista.seleccionar(id)) return;
    origenDetalle = origen; mapa.resaltar(id);
    actualizarEnlace(control.estado.actual.filtros, id, true);
    $('sq-titulo-detalle').focus(); $('sq-detalle').scrollIntoView({ block: 'nearest' });
}
function renderizar(e) {
    const anterior = !!e.actual && (e.fase !== 'listo' || e.borrador);
    $('sq-estado').dataset.fase = e.fase;
    $('sq-resultados').hidden = !e.actual;
    $('sq-resultados').setAttribute('aria-busy', String(e.fase === 'cargando'));
    $('sq-vigencia').textContent = anterior ? 'Resultado anterior' : 'Resultados';
    $('sq-restaurar').hidden = !e.actual || (!e.borrador && e.fase !== 'error');
    $('sq-reintentar').hidden = !['error', 'anterior'].includes(e.fase) || (!e.pendiente && !e.error);
    const titulos = { cargando: 'Cargando…', error: 'No se pudo completar la consulta', anterior: 'Resultado anterior', inicial: 'Elige territorio y mes', listo: 'Consulta disponible' };
    let titulo = titulos[e.fase], mensaje = '';
    if (e.error) {
        if (e.error.codigo === 'MUESTRAS_NO_DISPONIBLES') titulo = 'Sin datos para este mes';
        if (e.error.codigo === 'PERIODO_NO_DISPONIBLE') titulo = 'Datos no disponibles';
        const explicaciones = {
            MUESTRAS_NO_DISPONIBLES: 'No hay datos publicados para el territorio y el mes elegidos. Prueba otro territorio o período.',
            PERIODO_NO_DISPONIBLE: 'Los datos de este período no están disponibles. Prueba otras fechas o reintenta más tarde.',
            DATOS_INVALIDOS: 'No se pudo leer la información. Reintenta más tarde.',
        };
        mensaje = (explicaciones[e.error.codigo] ?? e.error.message) + (e.actual ? ' Se conserva la consulta anterior.' : '');
    } else if (e.borrador) mensaje = 'Aplica los cambios o restaura la consulta anterior.';
    else if (e.fase === 'cargando') mensaje = 'Espera unos segundos.';
    else if (e.fase === 'listo') {
        const r = e.actual?.respuesta;
        if (r && !r.datos.length) { titulo = 'Sin resultados'; mensaje = 'La respuesta válida no contiene muestras publicadas.'; }
        else if (r && !r.resumen.muestrasConIndice) { titulo = 'Muestras publicadas sin índice calculable'; mensaje = 'Consulta el motivo en cada ficha. Sin índice no significa normal ni cero.'; }
        else mensaje = r?.cobertura.estadoDatos === 'parcial' ? 'Cobertura parcial: hay muestras pendientes.' : 'Datos del período seleccionado.';
    } else mensaje = 'Elige territorio y mes.';
    $('sq-titulo-estado').textContent = titulo; $('sq-mensaje-estado').textContent = mensaje;
    if (e.actual && e.actual !== renderizado) {
        renderizado = e.actual; vista.mostrar(e.actual.respuesta);
        mapa.mostrar(e.actual.respuesta).catch(() => { $('sq-error-mapa').hidden = false; $('sq-error-mapa').textContent = 'No se pudo dibujar el mapa. Usa la lista y el detalle de las mismas muestras.'; });
        clearTimeout(vigencia);
        vigencia = setTimeout(() => control.verificarVigencia(), Math.max(1, Math.min(2147483647, Date.parse(e.actual.respuesta.consulta.obtencion.vigenteHasta) - Date.now() + 20)));
        if (seleccionPendiente) { const id = seleccionPendiente; seleccionPendiente = null; if (!vista.respuesta.datos.some(d => d.idMuestra === id)) { $('sq-mensaje-estado').textContent += ' La muestra enlazada no está publicada en este conjunto.'; } else seleccionar(id); }
    }
}

control = new ConsultaSequiasWeb($('visor-sequias').dataset.api, renderizar);
opciones($('sq-provincia'), catalogos.territorios.filter(t => t.tipo === 'provincia').map(t => [t.identificador, t.nombre]), 'Todo Santa Cruz');
actualizarMunicipios(); actualizarMuestras();
formulario.addEventListener('input', e => {
    if (e.target.id === 'sq-provincia') { actualizarMunicipios(''); actualizarMuestras(''); }
    if (e.target.id === 'sq-municipio') actualizarMuestras('');
    vista.cerrar(); mapa.resaltar(null); control.editar();
});
formulario.addEventListener('submit', e => {
    e.preventDefault();
    try {
        const filtros = validarFiltros(Object.fromEntries(new FormData(formulario)), catalogos);
        seleccionPendiente = null; actualizarEnlace(filtros); control.consultar(filtros);
    } catch (error) { control.editar(); control.emitir({ fase: 'error', error }); }
});
$('sq-restaurar').addEventListener('click', () => { aplicarFormulario(control.estado.actual.filtros); actualizarEnlace(control.estado.actual.filtros, null, true); control.restaurar(); });
$('sq-reintentar').addEventListener('click', () => control.reintentar());
$('sq-centrar').addEventListener('click', () => mapa.centrar());
$('sq-cerrar').addEventListener('click', () => { vista.cerrar(); mapa.resaltar(null); actualizarEnlace(control.estado.actual.filtros, null, true); (origenDetalle?.isConnected ? origenDetalle : $('sq-titulo-resultados')).focus(); });
function abrirEnlace() {
    try {
        const enlace = leerEnlace(location.search, catalogos);
        if (!enlace) { control.limpiar(); renderizado = null; return; }
        aplicarFormulario(enlace.filtros); seleccionPendiente = enlace.seleccion; control.consultar(enlace.filtros);
    } catch (error) { control.editar(); control.emitir({ fase: 'error', error: error instanceof Error ? error : new ErrorConsulta('Enlace no válido.') }); }
}
window.addEventListener('popstate', abrirEnlace);
document.addEventListener('visibilitychange', () => { if (!document.hidden) control.verificarVigencia(); });
abrirEnlace();
