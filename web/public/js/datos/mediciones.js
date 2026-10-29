import { iniciarMapaBase } from '../mapas/base.js';
import { presentacion } from './unidades.js';

const contenido = JSON.parse(document.getElementById('datos-mediciones').textContent);
const raizMapa = document.getElementById('mapa-datos');

function nombreFuente(fuente) {
    return { 'demo-local': 'Demostración', 'open-meteo-modelo': 'Open-Meteo' }[fuente] ?? fuente;
}

if (raizMapa && window.L) {
    const L = window.L;
    const seleccionado = contenido.seleccionado;
    const mapa = L.map(raizMapa, { scrollWheelZoom: true }).setView(
        seleccionado ? [seleccionado.latitud, seleccionado.longitud] : [-17, -63],
        seleccionado ? 8 : 3,
    );
    iniciarMapaBase(mapa, () => {
        document.getElementById('aviso-mapa-datos').textContent =
            'No se pudo cargar el fondo. Los puntos y registros siguen disponibles.';
    });

    const puntos = [...contenido.puntos];
    if (seleccionado && !puntos.some(punto => punto.codigo === seleccionado.codigo)) {
        puntos.push(seleccionado);
    }
    for (const punto of puntos) {
        const icono = L.divIcon({
            className: 'punto-ambiental' + (punto.codigo === seleccionado?.codigo ? ' seleccionado' : ''),
            html: '<span aria-hidden="true">⌁</span>',
            iconSize: [24, 24], iconAnchor: [12, 12],
        });
        const marcador = L.marker([punto.latitud, punto.longitud], {
            icon: icono, title: punto.nombre, alt: punto.nombre,
        }).addTo(mapa);
        const etiqueta = document.createElement('span');
        etiqueta.textContent = punto.nombre;
        marcador.bindTooltip(etiqueta);
        marcador.on('click', () => {
            const url = new URL(location.href);
            url.searchParams.set('punto', punto.codigo);
            url.searchParams.delete('pais');
            url.searchParams.delete('pagina');
            location.assign(url);
        });
    }
    mapa.on('click', evento => {
        const latitud = document.getElementById('latitud-punto');
        const longitud = document.getElementById('longitud-punto');
        if (latitud && longitud) {
            latitud.value = evento.latlng.lat.toFixed(6);
            longitud.value = evento.latlng.wrap().lng.toFixed(6);
        }
    });
}

const grafico = document.getElementById('grafico-datos');
const leyenda = document.getElementById('leyenda-datos');
const colores = { observado: '#1e8956', estimado: '#147eb4', simulado: '#c5661c' };

function crearSvg(tipo, atributos) {
    const elemento = document.createElementNS('http://www.w3.org/2000/svg', tipo);
    for (const [clave, valor] of Object.entries(atributos)) {
        elemento.setAttribute(clave, String(valor));
    }
    return elemento;
}

function dibujarHistorial() {
    grafico.replaceChildren();
    leyenda.replaceChildren();
    const datos = contenido.horas.map(d => ({...d, media: presentacion.numero(contenido.metrica, d.media, contenido.unidad)}));
    if (!datos.length) {
        const mensaje = document.createElement('p');
        mensaje.textContent = 'Sin registros para dibujar el gráfico.';
        grafico.append(mensaje);
        return;
    }
    const ancho = Math.max(260, Math.floor(grafico.getBoundingClientRect().width));
    const alto = 245;
    const margen = { izq: 52, der: 14, arr: 16, ab: 44 };
    const svg = crearSvg('svg', {
        viewBox: `0 0 ${ancho} ${alto}`, width: ancho, height: alto, 'aria-hidden': 'true',
    });
    const tiempos = datos.map(dato => Date.parse(dato.instante));
    const valores = datos.map(dato => Number(dato.media));
    const tMin = Math.min(...tiempos), tMax = Math.max(...tiempos);
    const vMin = Math.min(...valores), vMax = Math.max(...valores);
    const rango = vMax - vMin || 1;
    const x = tiempo => margen.izq + (tiempo - tMin) / (tMax - tMin || 1) * (ancho - margen.izq - margen.der);
    const y = valor => alto - margen.ab - (valor - vMin) / rango * (alto - margen.arr - margen.ab);
    const texto = (contenidoTexto, posicion) => {
        const elemento = crearSvg('text', { 'font-size': 16, fill: 'currentColor', ...posicion });
        elemento.textContent = contenidoTexto;
        svg.append(elemento);
    };
    for (const valor of [vMin, vMin + rango / 2, vMax === vMin ? vMin + rango : vMax]) {
        const posicionY = y(valor);
        svg.append(crearSvg('line', {
            x1: margen.izq, y1: posicionY, x2: ancho - margen.der, y2: posicionY,
            stroke: 'currentColor', opacity: 0.15,
        }));
        texto(valor.toLocaleString('es-BO', { maximumFractionDigits: 1 }), {
            x: margen.izq - 8, y: posicionY + 5, 'text-anchor': 'end',
        });
    }

    // Cada fuente y origen conserva su propia serie, aunque compartan hora.
    const grupos = new Map();
    for (const dato of datos) {
        const clave = dato.origen + '|' + dato.fuente;
        if (!grupos.has(clave)) grupos.set(clave, []);
        grupos.get(clave).push(dato);
    }
    for (const filas of grupos.values()) {
        const color = colores[filas[0].origen];
        svg.append(crearSvg('polyline', {
            points: filas.map(dato => `${x(Date.parse(dato.instante))},${y(dato.media)}`).join(' '),
            fill: 'none', stroke: color, 'stroke-width': 3,
        }));
        if (filas.length === 1) {
            svg.append(crearSvg('circle', {
                cx: x(Date.parse(filas[0].instante)), cy: y(filas[0].media), r: 4, fill: color,
            }));
        }
        const item = document.createElement('span');
        const linea = document.createElement('i');
        linea.style.backgroundColor = color;
        item.append(linea, document.createTextNode(
            contenido.origenes[filas[0].origen] + ' · ' + nombreFuente(filas[0].fuente),
        ));
        leyenda.append(item);
    }
    const formato = new Intl.DateTimeFormat('es-BO', {
        timeZone: 'America/La_Paz', day: '2-digit', month: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    });
    texto(formato.format(tMin), { x: margen.izq, y: alto - 10, 'text-anchor': 'start' });
    if (tMax !== tMin && ancho > 380) {
        texto(formato.format(tMax), { x: ancho - margen.der, y: alto - 10, 'text-anchor': 'end' });
    }
    grafico.append(svg);
}

if (grafico) {
    new ResizeObserver(dibujarHistorial).observe(grafico);
    document.addEventListener('unidades-cambiadas', dibujarHistorial);
}
