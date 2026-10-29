import { iniciarMapaBase } from './base.js';
import { consultaMonitoreo } from './consulta-monitoreo.js';
import { crearConsultaTiempo } from './tiempo.js';

const configuracion = JSON.parse(document.getElementById('configuracion-monitoreo').textContent);
const formulario = document.getElementById('consulta-monitoreo');
const estado = document.getElementById('estado-monitoreo');
const detalle = document.getElementById('mon-detalle');
const informacion = document.getElementById('mon-informacion');
const origenes = configuracion.origenes;
const paises = new Map(configuracion.paises.map(pais => [pais.codigo, pais.nombre]));
const L = window.L;
const mapa = L.map('mapa-monitoreo', { scrollWheelZoom: true, zoomAnimation: false, fadeAnimation: false }).setView([-20, -60], 3);
const marcadores = L.layerGroup().addTo(mapa);
const limites = L.geoJSON(null, {style:{color:'#f59e0b',weight:3,fillColor:'#fef3c7',fillOpacity:0.08},interactive:false}).addTo(mapa);
const porCodigo = new Map();
let redimension = null;
new ResizeObserver(() => {
    cancelAnimationFrame(redimension);
    redimension = requestAnimationFrame(() => mapa.invalidateSize({ animate: false }));
}).observe(document.getElementById('mapa-monitoreo'));
const formatoFecha = new Intl.DateTimeFormat('es-BO', {
    timeZone: 'America/La_Paz', dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23',
});
const iconos = {
    temperatura: '<path d="M9 14V5a3 3 0 0 1 6 0v9a5 5 0 1 1-6 0Z"/><path d="M12 8v9"/>',
    humedad: '<path d="M12 3C9 7 5 11 5 15a7 7 0 0 0 14 0c0-4-4-8-7-12Z"/>',
    ica: '<path d="M12 3C9 7 5 11 5 15a7 7 0 0 0 14 0c0-4-4-8-7-12Z"/><path d="m9 15 2 2 4-4"/>',
    aqi: '<path d="M3 8h12a3 3 0 1 0-3-3M3 12h16a3 3 0 1 1-3 3M3 16h6a3 3 0 1 1-3 3"/>',
    ruido: '<path d="m4 9 5 0 5-5v16l-5-5H4Z"/><path d="M18 8a6 6 0 0 1 0 8"/>',
};
let respuestaActual = null;
let puntoSeleccionado = null;
let marcaLectura = null;
let peticion = null;
let secuencia = 0;
let consultaBase = '';
let modoTiempo = false;
let cancelarTiempo = null;
const botonTiempo = document.getElementById('mon-tiempo');

if (matchMedia('(max-width: 767.98px)').matches) {
    document.getElementById('filtros-monitoreo').open = false;
}
const inicial = new URL(location.href).searchParams;
function cargarRegiones(elegida = '') {
    const control = formulario.elements.namedItem('region');
    control.replaceChildren(new Option('Todas las regiones', ''));
    for (const region of configuracion.regiones.filter(r => r.pais === formulario.elements.namedItem('pais').value)) {
        control.add(new Option(region.nombre, region.codigo));
    }
    control.value = elegida;
    if (control.selectedIndex < 0) control.value = '';
}
for (const campo of ['pais', 'metrica', 'origen', 'desde', 'hasta']) {
    const control = formulario.elements.namedItem(campo);
    const valor = inicial.get(campo);
    if (valor && (control.tagName !== 'SELECT' || [...control.options].some(opcion => opcion.value === valor))) {
        control.value = valor;
    }
}
cargarRegiones(inicial.get('region') ?? '');
formulario.elements.namedItem('pais').addEventListener('change', () => cargarRegiones());
puntoSeleccionado = inicial.get('punto');

iniciarMapaBase(mapa, mensaje => avisar(mensaje, true), 'mon-base');

function avisar(mensaje, error = false) {
    estado.textContent = mensaje;
    estado.dataset.error = String(error);
}

function nodo(tipo, texto, clase) {
    const elemento = document.createElement(tipo);
    if (texto !== undefined) elemento.textContent = texto;
    if (clase) elemento.className = clase;
    return elemento;
}

function nombreFuente(fuente) {
    return { 'demo-local': 'Demostración', 'open-meteo-modelo': 'Open-Meteo', manual: 'Carga manual' }[fuente] ?? fuente;
}

function centrar(latitud, longitud, zoom = 8) {
    const lateral = matchMedia('(min-width: 768px)').matches
        ? Math.min(detalle.offsetWidth + 32, mapa.getSize().x * 0.55) : 24;
    mapa.fitBounds([[latitud, longitud], [latitud, longitud]], {
        paddingTopLeft: [24, 80], paddingBottomRight: [lateral, 90],
        maxZoom: Math.max(mapa.getZoom(), zoom), animate: false,
    });
}

function cerrarDetalle() {
    cancelarTiempo?.(); cancelarTiempo = null;
    detalle.hidden = true;
    puntoSeleccionado = null;
    if (marcaLectura) mapa.removeLayer(marcaLectura);
    marcaLectura = null;
    for (const marcador of porCodigo.values()) marcador.getElement()?.classList.remove('seleccionado');
    const url = new URL(location.href);
    url.searchParams.delete('punto');
    history.replaceState(null, '', url);
}

function seleccionar(punto) {
    if (modoTiempo) { abrirTiempo(punto.latitud, punto.longitud, punto.nombre); return; }
    if (!respuestaActual) return;
    cerrarDetalle();
    puntoSeleccionado = punto.codigo;
    detalle.hidden = false;
    porCodigo.get(punto.codigo)?.getElement()?.classList.add('seleccionado');
    document.getElementById('mon-nombre').textContent = punto.nombre;
    informacion.replaceChildren();
    informacion.append(nodo('p', paises.get(punto.pais) ?? punto.pais));
    informacion.append(nodo('p', punto.tipo === 'referencia' ? 'Punto de referencia' : 'Punto de monitoreo'));
    if (!punto.lecturas.length) {
        informacion.append(nodo('p', 'Sin registros para esta variable, origen y período.'));
    } else {
        informacion.append(nodo('p', 'Última lectura del período, por origen y fuente.'));
    }
    for (const lectura of punto.lecturas) {
        const ficha = nodo('section', undefined, 'mon-lectura ' + lectura.origen);
        ficha.append(nodo('span', origenes[lectura.origen]));
        ficha.append(nodo('strong', lectura.valor.toLocaleString('es-BO', { maximumFractionDigits: 2 }) + ' ' + respuestaActual.unidad));
        ficha.append(nodo('p', formatoFecha.format(new Date(lectura.instante)) + ' · Bolivia'));
        ficha.append(nodo('p', nombreFuente(lectura.fuente)));
        if (lectura.origen === 'estimado') ficha.append(nodo('p', 'Estimación de modelo, no medición de una estación.'));
        if (lectura.origen === 'simulado') ficha.append(nodo('p', 'Dato simulado.'));
        const ubicacion = nodo('button', 'Ver ubicación de la lectura', 'btn btn-outline-primary');
        ubicacion.type = 'button';
        ubicacion.addEventListener('click', () => {
            if (marcaLectura) mapa.removeLayer(marcaLectura);
            marcaLectura = L.marker([lectura.latitud, lectura.longitud], { title: 'Ubicación de la lectura' }).addTo(mapa);
            centrar(lectura.latitud, lectura.longitud);
        });
        ficha.append(ubicacion);
        informacion.append(ficha);
    }
    if (respuestaActual.metrica === 'ica') {
        informacion.append(nodo('p', 'Este índice no sustituye un análisis de laboratorio del agua.'));
    }
    const historial = new URL(configuracion.historial, location.origin);
    historial.searchParams.set('punto', punto.codigo);
    historial.searchParams.set('metrica', respuestaActual.metrica);
    for (const campo of ['desde', 'hasta']) historial.searchParams.set(campo, formulario.elements.namedItem(campo).value);
    if (Object.hasOwn(origenes, respuestaActual.origen)) historial.searchParams.set('origen', respuestaActual.origen);
    const enlace = nodo('a', Object.hasOwn(origenes, respuestaActual.origen) ? 'Ver historial' : 'Ver historial completo', 'btn btn-primary');
    enlace.href = historial.href;
    informacion.append(enlace);
    const tiempo = crearConsultaTiempo({url: configuracion.tiempo, latitud: punto.latitud, longitud: punto.longitud});
    informacion.append(tiempo.elemento); cancelarTiempo = tiempo.cancelar;
    const url = new URL(location.href);
    url.searchParams.set('punto', punto.codigo);
    history.replaceState(null, '', url);
    centrar(punto.latitud, punto.longitud);
    if (matchMedia('(max-width: 767.98px)').matches) detalle.scrollIntoView({ block: 'nearest' });
}

function abrirTiempo(latitud, longitud, nombre = 'Tiempo del lugar') {
    cerrarDetalle(); modoTiempo = false;
    botonTiempo.setAttribute('aria-pressed', 'false'); botonTiempo.textContent = 'Tiempo de un lugar';
    document.getElementById('mapa-monitoreo').classList.remove('elegir-tiempo');
    detalle.hidden = false; document.getElementById('mon-nombre').textContent = nombre; informacion.replaceChildren();
    const tiempo = crearConsultaTiempo({url: configuracion.tiempo, latitud, longitud, automatico: true});
    informacion.append(tiempo.elemento); cancelarTiempo = tiempo.cancelar;
    marcaLectura = L.marker([latitud, longitud], {title: nombre}).addTo(mapa); centrar(latitud, longitud);
    avisar('Pronóstico del lugar elegido.');
    if (matchMedia('(max-width: 767.98px)').matches) detalle.scrollIntoView({block: 'nearest'});
}

botonTiempo.addEventListener('click', () => {
    modoTiempo = !modoTiempo; botonTiempo.setAttribute('aria-pressed', String(modoTiempo));
    botonTiempo.textContent = modoTiempo ? 'Cancelar elección' : 'Tiempo de un lugar';
    const superficie = document.getElementById('mapa-monitoreo'); superficie.classList.toggle('elegir-tiempo', modoTiempo);
    avisar(modoTiempo ? 'Pulsa un lugar del mapa. Con teclado, mueve el mapa y pulsa Enter.' : 'Selecciona un punto para consultar sus datos.');
    if (modoTiempo) { superficie.focus({preventScroll: true}); superficie.scrollIntoView({block: 'nearest'}); }
});
mapa.on('click', e => { if (modoTiempo) abrirTiempo(e.latlng.lat, e.latlng.lng); });
document.getElementById('mapa-monitoreo').addEventListener('keydown', e => {
    if (modoTiempo && e.key === 'Enter' && e.target.id === 'mapa-monitoreo') { e.preventDefault(); const centro = mapa.getCenter(); abrirTiempo(centro.lat, centro.lng); }
});

function centrarPuntos() {
    if (limites.getLayers().length) {
        mapa.fitBounds(limites.getBounds(), {padding:[35,70],maxZoom:9,animate:false});
        return;
    }
    if (!respuestaActual?.datos.length) {
        mapa.setView([-20, -60], 3);
        return;
    }
    mapa.fitBounds(respuestaActual.datos.map(punto => [punto.latitud, punto.longitud]), {
        padding: [45, 80], maxZoom: 7, animate: false,
    });
}

function dibujar() {
    limites.clearLayers();
    const fuenteLimite = document.getElementById('mon-fuente-limite');
    fuenteLimite.hidden = !respuestaActual.limite;
    if (respuestaActual.limite) {
        limites.addData(respuestaActual.limite);
        fuenteLimite.textContent = 'Límites de referencia: ' + respuestaActual.limite.properties.procedencia.fuente;
    }
    marcadores.clearLayers();
    porCodigo.clear();
    const simbolo = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + iconos[respuestaActual.metrica] + '</svg>';
    for (const punto of respuestaActual.datos) {
        const icono = L.divIcon({
            className: 'monitoreo-marcador' + (punto.lecturas.length ? ' con-datos' : ''),
            html: '<span>' + simbolo + '</span>', iconSize: [28, 28], iconAnchor: [14, 14],
        });
        const marcador = L.marker([punto.latitud, punto.longitud], { icon: icono, title: punto.nombre, alt: punto.nombre });
        marcador.bindTooltip(nodo('span', punto.nombre));
        marcador.on('click', () => seleccionar(punto));
        marcador.addTo(marcadores);
        marcador.getElement()?.addEventListener('keydown', evento => {
            if (evento.key === ' ') { evento.preventDefault(); seleccionar(punto); }
        });
        porCodigo.set(punto.codigo, marcador);
    }
    centrarPuntos();
    const seleccionado = respuestaActual.datos.find(punto => punto.codigo === puntoSeleccionado);
    if (seleccionado) seleccionar(seleccionado);
    else cerrarDetalle();
}

async function consultar(pagina = 1) {
    cancelarTiempo?.();
    peticion?.abort();
    const turno = ++secuencia;
    peticion = new AbortController();
    let parametros;
    try {
        parametros = consultaMonitoreo(Object.fromEntries(new FormData(formulario)));
    } catch (error) {
        cerrarDetalle();
        marcadores.clearLayers();
        limites.clearLayers();
        document.getElementById('mon-fuente-limite').hidden = true;
        porCodigo.clear();
        respuestaActual = null;
        document.getElementById('paginacion-monitoreo').hidden = true;
        avisar(error.message, true);
        return;
    }
    if (consultaBase && consultaBase !== parametros.toString()) {
        pagina = 1;
        const anterior = puntoSeleccionado;
        cerrarDetalle();
        puntoSeleccionado = anterior;
    }
    consultaBase = parametros.toString();
    parametros.set('pagina', String(pagina));
    const url = new URL(configuracion.api, location.origin);
    url.search = parametros.toString();
    const compartida = new URL(location.pathname, location.origin);
    for (const [clave, valor] of new FormData(formulario)) if (valor) compartida.searchParams.set(clave, valor);
    if (puntoSeleccionado) compartida.searchParams.set('punto', puntoSeleccionado);
    history.replaceState(null, '', compartida);
    detalle.hidden = true;
    if (marcaLectura) mapa.removeLayer(marcaLectura);
    marcaLectura = null;
    marcadores.clearLayers();
    limites.clearLayers();
    document.getElementById('mon-fuente-limite').hidden = true;
    porCodigo.clear();
    respuestaActual = null;
    document.getElementById('paginacion-monitoreo').hidden = true;
    avisar('Cargando…');
    try {
        const respuesta = await fetch(url, {
            headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: peticion.signal,
        });
        const cuerpo = await respuesta.json();
        if (turno !== secuencia) return;
        if (!respuesta.ok) throw new Error(cuerpo.error?.mensaje ?? 'No se pudo consultar. Vuelve a ingresar o revisa los filtros.');
        if (!Array.isArray(cuerpo.datos)) throw new Error('No se pudo leer la consulta. Inténtalo otra vez.');
        respuestaActual = cuerpo;
        dibujar();
        const conDatos = cuerpo.datos.filter(punto => punto.lecturas.length).length;
        const simulados = ['simulado', 'todos'].includes(cuerpo.origen) ? ' Incluye datos simulados cuando los hay.' : '';
        avisar(cuerpo.total
            ? `${cuerpo.datos.length} de ${cuerpo.total} puntos · ${conDatos} con datos del período.${simulados}`
            : 'No hay puntos activos en esta selección.');
        document.getElementById('paginacion-monitoreo').hidden = cuerpo.ultimaPagina <= 1;
        document.getElementById('mon-anterior').disabled = cuerpo.pagina <= 1;
        document.getElementById('mon-siguiente').disabled = cuerpo.pagina >= cuerpo.ultimaPagina;
    } catch (error) {
        if (turno !== secuencia || error.name === 'AbortError') return;
        avisar(error instanceof TypeError || error instanceof SyntaxError ? 'No se pudo conectar. Inténtalo otra vez.' : error.message, true);
    }
}

formulario.addEventListener('submit', evento => { evento.preventDefault(); consultar(); });
document.getElementById('mon-centrar').addEventListener('click', () => { cerrarDetalle(); centrarPuntos(); });
document.getElementById('mon-cerrar').addEventListener('click', cerrarDetalle);
document.getElementById('mon-anterior').addEventListener('click', () => consultar((respuestaActual?.pagina ?? 1) - 1));
document.getElementById('mon-siguiente').addEventListener('click', () => consultar((respuestaActual?.pagina ?? 1) + 1));
consultar();
