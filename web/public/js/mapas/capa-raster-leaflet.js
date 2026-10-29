import {coordenadaPixel, decodificar, color} from './raster.js';

export function crearCapaRaster(L, mapa, cuadro, pixeles, pane, opacidad) {
    const coloreados = new Uint8ClampedArray(pixeles.length);
    for (let i = 0; i < pixeles.length; i += 4) {
        const v = decodificar(cuadro.codificacion, ...pixeles.subarray(i, i + 4));
        coloreados.set(color(cuadro.capa, typeof v === 'object' && v !== null ? v.valor : v), i);
    }
    const capa = L.gridLayer({tileSize: 256, pane, opacity: opacidad, noWrap: true, updateWhenIdle: true,
        bounds: [[-85.051129, -180], [85.051129, 180]], attribution: null});
    capa.createTile = coords => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 256;
        const ctx = canvas.getContext('2d');
        const imagen = ctx.createImageData(256, 256), r = cuadro.rejilla;
        const xs = [], ys = [];
        for (let i = 0; i < 256; i++) {
            const lon = mapa.unproject(L.point(coords.x * 256 + i + .5, 0), coords.z).lng;
            const lat = mapa.unproject(L.point(0, coords.y * 256 + i + .5), coords.z).lat;
            const x = coordenadaPixel(cuadro, (r.sur + r.norte) / 2, lon)?.x;
            const y = coordenadaPixel(cuadro, lat, (r.oeste + r.este) / 2)?.y;
            xs[i] = x === undefined ? -1 : Math.max(0, Math.min(cuadro.ancho - 1, Math.round(x)));
            ys[i] = y === undefined ? -1 : Math.max(0, Math.min(cuadro.alto - 1, Math.round(y)));
        }
        for (let y = 0; y < 256; y++) {
            if (ys[y] < 0) continue;
            for (let x = 0; x < 256; x++) {
                if (xs[x] < 0) continue;
                const origen = (ys[y] * cuadro.ancho + xs[x]) * 4, destino = (y * 256 + x) * 4;
                for (let c = 0; c < 4; c++) imagen.data[destino + c] = coloreados[origen + c];
            }
        }
        ctx.putImageData(imagen, 0, 0);
        return canvas;
    };
    return capa;
}
