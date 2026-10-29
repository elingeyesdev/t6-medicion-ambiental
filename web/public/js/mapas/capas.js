import {crearCapaRaster} from './capa-raster-leaflet.js';
import {muestra, cuadrosDisponibles, crearReproductor, rampa} from './raster.js';
import {iniciarPoi} from './poi.js';
import {presentacion} from '../datos/unidades.js';

export function iniciarCapas(mapa, L = window.L) {
    const panel = document.getElementById('capas-mapa');
    if (!panel) return;
    const q = selector => panel.querySelector(selector);
    const estado = q('[data-estado]'), controles = q('[data-controles]'), visible = q('[data-visible]');
    const comparar = q('[data-comparar]'), barra = q('[data-recorrido]'), botonPlay = q('[data-reproducir]');
    const selectores = Object.fromEntries(['a', 'b'].map(id => [id, {capa: q(`[data-capa="${id}"]`), fecha: q(`[data-fecha="${id}"]`)}]));
    let catalogo = [], cargandoCatalogo, secuenciaCatalogo = 0, lecturaActiva = false, marcaLectura;
    const canales = {a: {}, b: {}}, memoria = new Map();
    const formato = new Intl.DateTimeFormat('es-BO', {timeZone: 'America/La_Paz', dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23'});
    const fecha = valor => formato.format(new Date(valor));
    const configurarPoi = iniciarPoi(panel, mapa, L);
    const divisor = document.createElement('div'); divisor.className = 'capas-divisor'; divisor.hidden = true;
    for (const texto of ['A', 'B']) { const n = document.createElement('span'); n.textContent = texto; divisor.append(n); }
    mapa.getContainer().append(divisor);
    for (const [id, z] of [['a', 250], ['b', 251]]) {
        const pane = mapa.createPane('ambiente-' + id); pane.style.zIndex = String(z); pane.style.pointerEvents = 'none';
    }
    function avisar(texto, error = false) { estado.textContent = texto; estado.dataset.error = String(error); }
    function capaElegida(id) { return catalogo.find(c => c.id === selectores[id].capa.value); }
    function cuadros(id) { return capaElegida(id) ? cuadrosDisponibles(capaElegida(id)) : []; }
    function elegido(id) { return cuadros(id).find(c => c.id === selectores[id].fecha.value); }
    function limpiar(id) {
        const canal = canales[id]; canal.turno = (canal.turno ?? 0) + 1; canal.peticion?.abort();
        if (canal.capa) mapa.removeLayer(canal.capa);
        canal.capa = canal.cuadro = canal.pixeles = null;
    }
    function leyendas() {
        const contenedor = q('[data-leyendas]'); contenedor.replaceChildren();
        for (const id of ['a', 'b']) {
            const c = canales[id].cuadro;
            if (!c || (id === 'b' && !comparar.checked)) continue;
            const variable = capaElegida(id), bloque = document.createElement('section'); bloque.className = 'capas-leyenda';
            const nombre = document.createElement('h3'); nombre.textContent = id.toUpperCase() + ' · ' + variable.nombre;
            const origen = document.createElement('p'); origen.textContent = fecha(c.instante) + ' · Bolivia · Estimado';
            const fuente = document.createElement('p'); fuente.textContent = c.fuente + ' · ' + c.producto;
            const escala = document.createElement('div'); escala.className = 'capas-escala'; escala.setAttribute('aria-hidden', 'true');
            const stops = rampa(variable.id), min = stops[0][0], max = stops.at(-1)[0];
            escala.style.background = 'linear-gradient(to right,' + stops.map(s => `${s[1]} ${(s[0] - min) / (max - min) * 100}%`).join(',') + ')';
            const marcas = document.createElement('ul'); marcas.className = 'capas-marcas';
            for (const [valor, color] of stops) {
                const n = document.createElement('li'), muestraColor = document.createElement('i'); muestraColor.style.background = color;
                muestraColor.setAttribute('aria-hidden', 'true'); n.append(muestraColor, document.createTextNode(presentacion.texto(variable.id, valor, variable.unidad))); marcas.append(n);
            }
            const sin = document.createElement('p'); sin.textContent = 'Transparente: sin dato. Escala de colores limitada en los extremos.';
            bloque.append(nombre, origen, fuente, escala, marcas, sin); contenedor.append(bloque);
        }
    }
    function dividir() {
        const proporcion = Number(q('[data-division]').value) / 100;
        const nw = mapa.containerPointToLayerPoint([0, 0]), se = mapa.containerPointToLayerPoint(mapa.getSize());
        const x = nw.x + (se.x - nw.x) * proporcion;
        const recortar = (id, izq, der) => { mapa.getPane('ambiente-' + id).style.clip = `rect(${nw.y}px, ${der}px, ${se.y}px, ${izq}px)`; };
        if (comparar.checked) { recortar('a', nw.x, x); recortar('b', x, se.x); }
        else { mapa.getPane('ambiente-a').style.clip = ''; mapa.getPane('ambiente-b').style.clip = ''; }
        divisor.hidden = !comparar.checked || !visible.checked;
        divisor.style.left = proporcion * 100 + '%';
        q('[data-porcentaje]').textContent = `${Math.round(proporcion * 100)} % de A · ${Math.round((1 - proporcion) * 100)} % de B`;
    }
    mapa.on('move zoom resize', dividir);
    function sincronizar() {
        const indice = cuadros('a').findIndex(c => c.id === selectores.a.fecha.value);
        barra.max = String(Math.max(0, cuadros('a').length - 1)); barra.value = String(Math.max(0, indice));
        barra.setAttribute('aria-valuetext', elegido('a') ? fecha(elegido('a').instante) + ' · Bolivia' : 'Sin imágenes');
        const unica = cuadros('a').length < 2;
        barra.disabled = unica; botonPlay.disabled = unica;
        q('[data-anterior]').disabled = unica || indice <= 0;
        q('[data-siguiente]').disabled = unica || indice >= cuadros('a').length - 1;
        q('[data-seleccion-b]').hidden = !comparar.checked;
        dividir();
    }
    function cargarFechas(id, instantePreferido) {
        const opciones = cuadros(id);
        selectores[id].fecha.replaceChildren(...opciones.map(c => new Option(fecha(c.instante), c.id)));
        const previa = opciones.find(c => c.instante === instantePreferido);
        selectores[id].fecha.value = previa?.id ?? opciones.at(-1)?.id ?? '';
        sincronizar();
    }
    async function obtener(c, signal) {
        if (memoria.has(c.id)) return memoria.get(c.id);
        const url = new URL(c.url, location.origin);
        if (url.origin !== location.origin || !/\/mapa\/capas\/cuadros\/[a-z0-9_-]+\.png$/.test(url.pathname) || url.search) throw new Error('No se puede abrir esta imagen.');
        const r = await fetch(url, {headers: {Accept: 'image/png, application/json'}, credentials: 'same-origin', signal});
        if (!r.ok) {
            const error = await r.json().catch(() => ({}));
            throw new Error(error.error?.mensaje ?? 'No se pudo cargar esta imagen. Elige otra fecha.');
        }
        if (!r.headers.get('Content-Type')?.startsWith('image/png')) throw new Error('No se pudo leer esta imagen.');
        const blob = await r.blob();
        if (blob.size > 5242880 || c.ancho * c.alto > 2200000) throw new Error('Esta imagen no se puede abrir.');
        const imagen = await createImageBitmap(blob, {premultiplyAlpha: 'none', colorSpaceConversion: 'none'});
        try {
            if (imagen.width !== c.ancho || imagen.height !== c.alto) throw new Error('No se pudo leer esta imagen.');
            const canvas = document.createElement('canvas'); canvas.width = c.ancho; canvas.height = c.alto;
            const ctx = canvas.getContext('2d', {willReadFrequently: true}); ctx.drawImage(imagen, 0, 0);
            const pixeles = ctx.getImageData(0, 0, c.ancho, c.alto).data;
            if (signal.aborted) throw new DOMException('Cancelado', 'AbortError');
            memoria.set(c.id, pixeles);
            while (memoria.size > 3) memoria.delete(memoria.keys().next().value);
            return pixeles;
        } finally { imagen.close(); }
    }
    function leer() {
        const centro = mapa.getCenter(), salida = q('[data-lectura]'); salida.replaceChildren();
        if (!visible.checked) return;
        const ubicacion = document.createElement('p'); ubicacion.textContent = `Centro: ${centro.lat.toFixed(4)}, ${centro.lng.toFixed(4)}`; salida.append(ubicacion);
        for (const id of ['a', 'b']) {
            if (id === 'b' && !comparar.checked) continue;
            const c = canales[id].cuadro, variable = capaElegida(id), n = document.createElement('p');
            const valor = c ? muestra(c, canales[id].pixeles, centro.lat, centro.lng, true) : null;
            n.textContent = `${id.toUpperCase()} · ${variable?.nombre ?? 'Capa'}: ${presentacion.texto(variable?.id, valor, variable?.unidad ?? '')}`;
            salida.append(n);
        }
        if (marcaLectura) mapa.removeLayer(marcaLectura);
        marcaLectura = L.circleMarker(centro, {radius: 6, color: '#fff', weight: 2, fillColor: '#193c52', fillOpacity: 1, interactive: false}).addTo(mapa);
    }
    async function pintarCanal(id) {
        limpiar(id); leyendas();
        q('[data-lectura]').replaceChildren();
        if (marcaLectura) mapa.removeLayer(marcaLectura); marcaLectura = null;
        if (!visible.checked || (id === 'b' && !comparar.checked)) return;
        const cuadro = elegido(id); if (!cuadro) return;
        const canal = canales[id], turno = canal.turno;
        canal.peticion = new AbortController();
        try {
            const pixeles = await obtener(cuadro, canal.peticion.signal);
            if (turno !== canal.turno || !visible.checked) return;
            canal.cuadro = {...cuadro, capa: capaElegida(id).id}; canal.pixeles = pixeles;
            canal.capa = crearCapaRaster(L, mapa, canal.cuadro, pixeles, 'ambiente-' + id, Number(q('[data-opacidad]').value) / 100).addTo(mapa);
            leyendas(); dividir(); if (lecturaActiva) leer();
        } catch (e) {
            if (turno !== canal.turno || e.name === 'AbortError') return;
            throw new Error(id.toUpperCase() + ': ' + (e instanceof TypeError || e instanceof SyntaxError ? 'No se pudo cargar la imagen. Inténtalo de nuevo.' : e.message));
        }
    }
    const reproductor = crearReproductor({
        cantidad: () => cuadros('a').length, intervalo: 1400,
        mostrar: async i => {
            selectores.a.fecha.value = cuadros('a')[i].id; sincronizar();
            await Promise.all([pintarCanal('a'), canales.b.cuadro ? Promise.resolve() : pintarCanal('b')]);
            if (reproductor.activo) avisar('Mostrando A · ' + fecha(elegido('a').instante) + ' · Bolivia.');
        },
        cambiar: activo => { botonPlay.textContent = activo ? 'Pausar' : 'Reproducir'; botonPlay.setAttribute('aria-pressed', String(activo)); },
    });
    async function pintar() {
        sincronizar();
        if (marcaLectura) mapa.removeLayer(marcaLectura); marcaLectura = null; q('[data-lectura]').replaceChildren();
        const actualA = canales.a.turno ?? 0;
        avisar(visible.checked ? 'Cargando imágenes…' : 'Elige Mostrar capa para ver las imágenes.');
        const resultados = await Promise.allSettled(['a', 'b'].map(pintarCanal));
        if ((canales.a.turno ?? 0) !== actualA + 1) return;
        const errores = resultados.filter(r => r.status === 'rejected').map(r => r.reason.message);
        if (errores.length) { reproductor.parar(); avisar(errores.join(' '), true); }
        else if (visible.checked) avisar(comparar.checked ? 'A a la izquierda · B a la derecha. Cada capa conserva su fecha y escala.' : 'Capa visible. Las zonas transparentes no tienen datos.');
    }
    async function actualizar() {
        reproductor.parar(); cargandoCatalogo?.abort(); const turno = ++secuenciaCatalogo;
        cargandoCatalogo = new AbortController(); limpiar('a'); limpiar('b'); memoria.clear(); leyendas();
        visible.checked = false; visible.disabled = true; controles.disabled = true; sincronizar(); avisar('Consultando imágenes disponibles…');
        try {
            const r = await fetch(panel.dataset.catalogo, {headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: cargandoCatalogo.signal});
            const datos = await r.json(); if (turno !== secuenciaCatalogo) return;
            if (!r.ok) throw new Error(datos.error?.mensaje ?? 'No se pueden consultar las capas. Vuelve a ingresar o inténtalo otra vez.');
            if (datos.version !== 1 || !Array.isArray(datos.capas)) throw new Error('No se pudo leer la lista de capas.');
            catalogo = datos.capas; configurarPoi(datos.poi);
            for (const id of ['a', 'b']) {
                selectores[id].capa.replaceChildren(...catalogo.filter(c => c.cuadros.length).map(c => {
                    const n = new Option(c.nombre + (c.cuadros.length ? '' : ' · sin imágenes'), c.id); n.disabled = !c.cuadros.length; return n;
                }));
                selectores[id].capa.value = catalogo.find(c => c.cuadros.length)?.id ?? '';
                cargarFechas(id);
            }
            const disponible = catalogo.some(c => c.cuadros.length);
            controles.disabled = !disponible; visible.disabled = !disponible;
            avisar(disponible ? 'Elige Mostrar capa para ver las imágenes.' + (datos.faltantes ? ' Algunas imágenes no están disponibles.' : '') : 'Todavía no hay imágenes ambientales disponibles. Puedes seguir consultando los puntos.');
        } catch (e) {
            if (turno !== secuenciaCatalogo || e.name === 'AbortError') return;
            configurarPoi({habilitado: false, paises: {}});
            avisar(e instanceof TypeError || e instanceof SyntaxError ? 'No se pudo conectar. Inténtalo de nuevo.' : e.message, true);
        }
    }
    q('[data-actualizar]').addEventListener('click', actualizar);
    visible.addEventListener('change', () => { reproductor.parar(); pintar(); });
    comparar.addEventListener('change', () => { reproductor.parar(); pintar(); });
    for (const id of ['a', 'b']) {
        selectores[id].capa.addEventListener('change', () => { reproductor.parar(); const anterior = elegido(id)?.instante; cargarFechas(id, anterior); pintar(); });
        selectores[id].fecha.addEventListener('change', () => { reproductor.parar(); pintar(); });
    }
    function ir(indice) { reproductor.parar(); const c = cuadros('a')[indice]; if (c) { selectores.a.fecha.value = c.id; pintar(); } }
    barra.addEventListener('input', () => ir(Number(barra.value)));
    q('[data-anterior]').addEventListener('click', () => ir(Number(barra.value) - 1));
    q('[data-siguiente]').addEventListener('click', () => ir(Number(barra.value) + 1));
    botonPlay.addEventListener('click', () => {
        if (reproductor.activo) { reproductor.parar(); return; }
        if (!visible.checked) visible.checked = true;
        const indice = Number(barra.value), desde = indice >= cuadros('a').length - 1 ? 0 : indice;
        reproductor.iniciar(desde).catch(e => avisar(e.message, true));
    });
    q('[data-division]').addEventListener('input', dividir);
    q('[data-opacidad]').addEventListener('input', () => { for (const canal of Object.values(canales)) canal.capa?.setOpacity(Number(q('[data-opacidad]').value) / 100); });
    q('[data-leer]').addEventListener('click', () => { lecturaActiva = true; leer(); });
    document.addEventListener('unidades-cambiadas', () => { leyendas(); if (lecturaActiva) leer(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden) reproductor.parar(); });
    window.addEventListener('pagehide', () => { reproductor.parar(); cargandoCatalogo?.abort(); limpiar('a'); limpiar('b'); memoria.clear(); });
    actualizar();
}
