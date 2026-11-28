import { iniciarMapaBase } from './base.js';

const visor = document.getElementById('forestal-publicado');
const formulario = document.getElementById('forestal-periodo');
const desde = document.getElementById('forestal-desde');
const hasta = document.getElementById('forestal-hasta');
const estado = document.getElementById('forestal-estado');
const leyenda = document.getElementById('forestal-leyenda');
const centrar = document.getElementById('forestal-centrar');
const consultar = formulario.querySelector('button[type="submit"]');
const urlCapa = 'https://tiles.globalforestwatch.org/umd_tree_cover_loss';
const credito = 'Pérdida arbórea © <a href="https://www.globalforestwatch.org/">Global Forest Watch</a> / UMD';

function urlTesela({ z, x, y }, inicio, fin) {
    const filtros = new URLSearchParams({
        start_year: inicio, end_year: fin,
        tree_cover_density_threshold: visor.dataset.copa,
        render_type: 'true_color',
    });
    return urlCapa + '/' + encodeURIComponent(visor.dataset.version) + '/dynamic/' + z + '/' + x + '/' + y + '.png?' + filtros;
}

function capaRecortada(L, mapa, geometria, limites, inicio, fin) {
    const poligonos = geometria.type === 'Polygon' ? [geometria.coordinates] : geometria.coordinates;
    const proyecciones = new Map();
    const capa = L.gridLayer({ bounds: limites, noWrap: true, maxNativeZoom: 14, maxZoom: 20, attribution: credito, pane: 'forestal-observaciones' });
    capa.createTile = function (coords, hecho) {
        const canvas = document.createElement('canvas');
        const tam = this.getTileSize().x;
        // El proveedor sirve imágenes de 512 px en la cuadrícula XYZ de 256 px.
        const escala = 2;
        canvas.width = canvas.height = tam * escala;
        const imagen = new Image();
        imagen.referrerPolicy = 'no-referrer';
        imagen.onload = () => {
            if (!proyecciones.has(coords.z)) {
                proyecciones.set(coords.z, poligonos.flatMap(poligono => poligono.map(anillo =>
                    anillo.map(([lon, lat]) => mapa.project([lat, lon], coords.z)))));
            }
            const ctx = canvas.getContext('2d');
            if (!ctx) { hecho(new Error('No se pudo dibujar la capa'), canvas); return; }
            ctx.scale(escala, escala);
            ctx.beginPath();
            for (const anillo of proyecciones.get(coords.z)) {
                for (let i = 0; i < anillo.length; i++) {
                    const x = anillo[i].x - coords.x * tam;
                    const y = anillo[i].y - coords.y * tam;
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                }
                ctx.closePath();
            }
            ctx.clip('evenodd');
            ctx.drawImage(imagen, 0, 0, tam, tam);
            hecho(null, canvas);
        };
        imagen.onerror = () => hecho(new Error('No se pudo descargar una imagen de la capa'), canvas);
        imagen.src = urlTesela(coords, inicio, fin);
        return canvas;
    };
    return capa;
}

async function iniciar() {
    consultar.disabled = true;
    const L = window.L;
    if (!L) throw new Error('No se pudo iniciar el mapa.');
    const mapa = L.map('forestal-mapa', { maxZoom: 20 }).setView([-17.5, -62.1], 6);
    const observaciones = mapa.createPane('forestal-observaciones');
    observaciones.style.zIndex = '350';
    observaciones.style.pointerEvents = 'none';
    iniciarMapaBase(mapa, mensaje => {
        const aviso = document.getElementById('forestal-aviso-base');
        aviso.textContent = mensaje;
        aviso.hidden = false;
    }, 'forestal-base');
    const respuesta = await fetch(visor.dataset.limite, { signal: AbortSignal.timeout(12000), headers: { Accept: 'application/json' } });
    if (!respuesta.ok) throw new Error('No se pudo cargar el límite de Santa Cruz. Vuelve a cargar la página.');
    const { limite } = await respuesta.json();
    if (!['Polygon', 'MultiPolygon'].includes(limite?.geometry?.type)) throw new Error('El límite de Santa Cruz no está disponible.');
    const borde = L.geoJSON(limite, { style: { color: '#355445', weight: 2, fill: false }, interactive: false }).addTo(mapa);
    const limites = borde.getBounds();
    const encuadrar = () => mapa.fitBounds(limites, { padding: [18, 18] });
    centrar.addEventListener('click', encuadrar);
    centrar.disabled = false;
    consultar.disabled = false;
    encuadrar();

    const linea = document.getElementById('forestal-linea');
    const anioElegido = document.getElementById('forestal-anio-elegido');
    linea.disabled = false;
    linea.addEventListener('input', () => { anioElegido.value = linea.value; });
    linea.addEventListener('change', () => {
        desde.value = linea.value;
        hasta.value = linea.value;
        hasta.setCustomValidity('');
        aplicar();
    });

    let capa;
    function aplicar() {
        hasta.setCustomValidity(desde.valueAsNumber > hasta.valueAsNumber ? 'El año final debe ser igual o posterior al inicial.' : '');
        if (!formulario.reportValidity()) {
            estado.textContent = 'Revisa los años solicitados. La consulta anterior se conserva si ya estaba cargada.';
            return;
        }
        const inicio = desde.valueAsNumber;
        const fin = hasta.valueAsNumber;
        linea.value = fin;
        anioElegido.value = inicio === fin ? String(fin) : inicio + '–' + fin;
        const enlace = new URL(location.href);
        enlace.searchParams.set('desde', String(inicio));
        enlace.searchParams.set('hasta', String(fin));
        history.replaceState(null, '', enlace);
        if (capa) mapa.removeLayer(capa);
        estado.textContent = 'Cargando observaciones satelitales…';
        leyenda.hidden = true;
        let cargadas = 0;
        let fallos = 0;
        const nueva = capaRecortada(L, mapa, limite.geometry, limites, inicio, fin);
        capa = nueva;
        nueva.on('loading', () => { cargadas = 0; fallos = 0; });
        nueva.on('tileload', () => { cargadas++; });
        nueva.on('tileerror', () => { fallos++; });
        nueva.on('load', () => {
            if (capa !== nueva) return;
            estado.textContent = fallos === 0 ? 'Observaciones cargadas.' : cargadas > 0
                ? 'Algunas imágenes no cargaron. Pulsa Consultar para reintentar.'
                : 'La capa satelital no está disponible en este momento. Pulsa Consultar para reintentar.';
            document.getElementById('forestal-anios').textContent = inicio === fin ? String(fin) : inicio + '–' + fin;
            leyenda.hidden = cargadas === 0 && fallos > 0;
        });
        nueva.addTo(mapa);
    }
    formulario.addEventListener('input', () => hasta.setCustomValidity(''));
    formulario.addEventListener('submit', evento => { evento.preventDefault(); aplicar(); });
    const parametros = new URLSearchParams(location.search);
    if (parametros.has('desde') || parametros.has('hasta')) {
        desde.value = parametros.get('desde') ?? '';
        hasta.value = parametros.get('hasta') ?? '';
    }
    aplicar();
}

iniciar().catch(error => { estado.textContent = error.message || 'No se pudo cargar la cobertura forestal.'; });
