// Codificaciones explícitas de ETL y texturas del referente. Nunca inferirlas por el nombre del PNG.
export const lluviaEscalones = [0, .2, .5, 1, 2, 3, 4, 5, 7.5, 10, 15, 20, 25, 30, 35, 40, 50, 60, 70, 85, 100, 150];

export function decodificar(codigo, r, g, b, a) {
    if (a === 0) return null;
    switch (codigo) {
        case 'temp-era5': return r / 255 * 100 - 50;
        case 'temp-textura': return r / 255 * 120 - 60;
        case 'lluvia-escalones': {
            const i = r / 255 * 21, inferior = Math.min(20, Math.floor(i));
            return lluviaEscalones[inferior] + (i - inferior) * (lluviaEscalones[inferior + 1] - lluviaEscalones[inferior]);
        }
        case 'viento-uv': {
            const u = (r / 255 * 200 - 100) * 3.6, v = (g / 255 * 200 - 100) * 3.6;
            return {u, v, valor: Math.hypot(u, v)};
        }
        case 'viento-uv127': {
            // El byte 127 representa calma exacta; ambas componentes están en m/s.
            const u = (r - 127) / 1.27 * 3.6, v = (g - 127) / 1.27 * 3.6;
            return {u, v, valor: Math.hypot(u, v)};
        }
        case 'viento-direccion': {
            const velocidad = r / 255 * 150, angulo = (g / 255 * 360 + 180) * Math.PI / 180;
            return {u: velocidad * Math.sin(angulo), v: velocidad * Math.cos(angulo), valor: velocidad};
        }
        case 'vis-etl': return r / 255 * 24.140;
        case 'vis-textura': return r / 255 * 24.135;
        case 'nieve-etl': return r / 255 * 150;
        case 'nieve-fresca-textura': return g / 255 * 300;
        case 'aqi-textura': return r * 2;
        case 'humedad-etl': return r / 255 * 100;
        case 'presion-etl': return r / 255 * 200 + 900;
        case 'solar-wm2': return r / 255 * 1600;
        case 'evap-pot-wm2': return r <= 64 ? (r - 64) / 64 * 500 : (r - 64) / 191 * 1500;
        default: throw new Error('Codificación de capa desconocida.');
    }
}

export function coordenadaPixel(cuadro, latitud, longitud) {
    if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) return null;
    const r = cuadro.rejilla, ancho = r.este - r.oeste;
    let lon = ((longitud - r.oeste) % 360 + 360) % 360 + r.oeste;
    if (lon > r.este || latitud < r.sur || latitud > r.norte) return null;
    if (ancho === 360 && lon === r.este) lon = r.oeste;
    const centros = r.registro === 'centros';
    const x = (lon - r.oeste) / ancho * (cuadro.ancho - (centros ? 0 : 1)) - (centros ? .5 : 0);
    let y = (latitud - r.sur) / (r.norte - r.sur);
    if (r.filas === 'norte-sur') y = 1 - y;
    return {x, y: y * (cuadro.alto - (centros ? 0 : 1)) - (centros ? .5 : 0)};
}

function muestrear(cuadro, pixeles, latitud, longitud, interpolar = false) {
    const coordenada = coordenadaPixel(cuadro, latitud, longitud);
    if (!coordenada || pixeles.length !== cuadro.ancho * cuadro.alto * 4) return null;
    function leer(x, y) {
        const global = cuadro.rejilla.este - cuadro.rejilla.oeste === 360;
        // En nodos, la última columna duplica el meridiano inicial.
        const periodo = cuadro.ancho - (cuadro.rejilla.registro === 'nodos' ? 1 : 0);
        x = global ? ((x % periodo) + periodo) % periodo : Math.max(0, Math.min(cuadro.ancho - 1, x));
        y = Math.max(0, Math.min(cuadro.alto - 1, y));
        const i = (y * cuadro.ancho + x) * 4;
        return decodificar(cuadro.codificacion, ...pixeles.subarray(i, i + 4));
    }
    const {x, y} = coordenada;
    if (!interpolar || cuadro.codificacion === 'aqi-textura') {
        return leer(Math.round(x), Math.round(y));
    }
    const x0 = Math.floor(x), y0 = Math.floor(y), fx = x - x0, fy = y - y0;
    const vecinos = [[x0, y0, (1 - fx) * (1 - fy)], [x0 + 1, y0, fx * (1 - fy)],
        [x0, y0 + 1, (1 - fx) * fy], [x0 + 1, y0 + 1, fx * fy]].filter(v => v[2] > 1e-10);
    let suma = 0, u = 0, v = 0, vector = false;
    for (const [cx, cy, peso] of vecinos) {
        const valor = leer(cx, cy);
        if (valor === null) return null;
        if (typeof valor === 'object') { vector = true; u += valor.u * peso; v += valor.v * peso; }
        else suma += valor * peso;
    }
    return vector ? {u, v, valor: Math.hypot(u, v)} : suma;
}

export function muestra(cuadro, pixeles, latitud, longitud, interpolar = false) {
    const valor = muestrear(cuadro, pixeles, latitud, longitud, interpolar);
    return typeof valor === 'object' && valor !== null ? valor.valor : valor;
}

export function esViento(cuadro) {
    return ['viento-uv', 'viento-uv127', 'viento-direccion'].includes(cuadro?.codificacion);
}

export function muestraVector(cuadro, pixeles, latitud, longitud) {
    return esViento(cuadro) ? muestrear(cuadro, pixeles, latitud, longitud, true) : null;
}

const rampas = {
    temperatura: [[-60, '#101060'], [-30, '#5969dc'], [0, '#3ad8db'], [15, '#42bb51'], [30, '#ffe132'], [45, '#e6361d'], [60, '#800000']],
    lluvia: [[0, '#e5f8ff'], [.2, '#64d9fa'], [2, '#297cd9'], [10, '#2349c2'], [30, '#9d37ae'], [150, '#f2beff']],
    viento: [[0, '#afdcff'], [30, '#44c954'], [60, '#ffc928'], [100, '#d74726'], [150, '#b962a9']],
    visibilidad: [[0, '#883c21'], [1, '#c87934'], [5, '#e3bb76'], [10, '#eee4bc'], [24.14, '#eff7ff']],
    nieve: [[0, '#f8fcff'], [15, '#adefff'], [50, '#45badf'], [100, '#246eae'], [300, '#601c8c']],
    aqi: [[0, '#d4f1e0'], [50, '#59b633'], [100, '#edda30'], [150, '#ef8a35'], [200, '#cc3a3f'], [300, '#9d4a98'], [510, '#70152e']],
    humedad: [[0, '#dec08b'], [25, '#eadca8'], [50, '#b2e5dc'], [75, '#4fabbf'], [100, '#265ba5']],
    presion: [[900, '#684ab0'], [950, '#71a6d4'], [1000, '#bce5be'], [1050, '#f5bc67'], [1100, '#a33634']],
    radiacion_solar: [[0, '#fff6d5'], [200, '#ffdf72'], [500, '#f5a33b'], [800, '#df592c'], [1200, '#a32131'], [1600, '#641d44']],
    evaporacion_potencial: [[-500, '#3657a6'], [0, '#d7edf2'], [250, '#a9cf81'], [500, '#f4d36a'], [1000, '#dc7738'], [1500, '#a6352c']],
};
rampas.lluvia_intensidad = rampas.lluvia;
export function rampa(capa) { return rampas[capa] ?? []; }
export function color(capa, valor) {
    if (valor === null || !Number.isFinite(valor)) return [0, 0, 0, 0];
    const stops = rampa(capa);
    if (!stops.length) throw new Error('Capa desconocida.');
    let i = stops.findIndex(s => s[0] >= valor);
    if (i < 0) i = stops.length - 1;
    const a = stops[Math.max(0, i - 1)], b = stops[i];
    const t = a[0] === b[0] ? 0 : Math.max(0, Math.min(1, (valor - a[0]) / (b[0] - a[0])));
    const rgb = hex => [1, 3, 5].map(pos => parseInt(hex.slice(pos, pos + 2), 16));
    const primero = rgb(a[1]), segundo = rgb(b[1]);
    return primero.map((c, k) => Math.round(c + (segundo[k] - c) * t)).concat(255);
}

// Solo fechas enumeradas. No rellenar horas, ni sustituir por la imagen más próxima.
export function cuadrosDisponibles(capa) {
    return [...capa.cuadros].sort((a, b) => a.instante.localeCompare(b.instante));
}

export function crearReproductor({mostrar, cantidad, cambiar = () => {}, intervalo = 1000}) {
    let turno = 0, activo = false, temporizador, despertar;
    const parar = () => { turno++; activo = false; clearTimeout(temporizador); despertar?.(); cambiar(false); };
    return {
        parar,
        get activo() { return activo; },
        async iniciar(desde = 0) {
            parar();
            const actual = turno;
            activo = true; cambiar(true);
            try {
                for (let i = desde; i < cantidad() && actual === turno; i++) {
                    await mostrar(i);
                    if (actual !== turno) break;
                    await new Promise(resolve => { despertar = resolve; temporizador = setTimeout(resolve, intervalo); });
                }
            } finally {
                if (actual === turno) { activo = false; cambiar(false); }
            }
        },
    };
}
