// Clasificaciones WMO de Open-Meteo; no son umbrales ni alertas del sistema.
const condiciones = new Map([
    [0, ['Despejado', 'sol']], [1, ['Mayormente despejado', 'nube-sol']],
    [2, ['Parcialmente nublado', 'nube-sol']], [3, ['Nublado', 'nube']],
    [45, ['Niebla', 'niebla']], [48, ['Niebla con escarcha', 'niebla']],
    [51, ['Llovizna ligera', 'llovizna']], [53, ['Llovizna moderada', 'llovizna']], [55, ['Llovizna densa', 'llovizna']],
    [56, ['Llovizna helada ligera', 'lluvia-helada']], [57, ['Llovizna helada densa', 'lluvia-helada']],
    [61, ['Lluvia ligera', 'lluvia']], [63, ['Lluvia moderada', 'lluvia']], [65, ['Lluvia fuerte', 'lluvia']],
    [66, ['Lluvia helada ligera', 'lluvia-helada']], [67, ['Lluvia helada fuerte', 'lluvia-helada']],
    [71, ['Nieve ligera', 'nieve']], [73, ['Nieve moderada', 'nieve']], [75, ['Nieve fuerte', 'nieve']], [77, ['Nieve granulada', 'nieve']],
    [80, ['Chubascos ligeros', 'lluvia']], [81, ['Chubascos moderados', 'lluvia']], [82, ['Chubascos fuertes', 'lluvia']],
    [85, ['Chubascos de nieve ligeros', 'nieve']], [86, ['Chubascos de nieve fuertes', 'nieve']],
    [95, ['Tormenta eléctrica', 'tormenta']], [96, ['Tormenta con granizo ligero', 'granizo']],
    [97, ['Tormenta fuerte', 'tormenta']], [99, ['Tormenta con granizo fuerte', 'granizo']],
]);

export function condicionTiempo(codigo, esDia = null) {
    const valor = Number.isInteger(codigo) ? condiciones.get(codigo) : null;
    if (!valor) return {nombre: 'Sin dato', icono: 'sin-dato'};
    let [nombre, icono] = valor;
    if (esDia === false && icono === 'sol') icono = 'luna';
    if (esDia === false && icono === 'nube-sol') icono = 'nube-luna';
    return {nombre, icono};
}

const nube = 'M6 17h12a4 4 0 0 0 .4-8 6 6 0 0 0-11.5-2A5 5 0 0 0 6 17Z';
const dibujos = {
    sol: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5',
    luna: 'M20 15A8 8 0 0 1 9 4a8 8 0 1 0 11 11Z',
    'nube-sol': 'M9 3v2M2 10h2m-.5-6.5L5 5m9-1 1-1M5.5 11a4 4 0 1 1 7.5-3M7 20h11a4 4 0 0 0 0-8 5 5 0 0 0-9-2 5 5 0 0 0-2 10Z',
    'nube-luna': 'M16 3a5 5 0 0 0 6 7M16 3a6 6 0 0 1-6 7M7 21h11a4 4 0 0 0 0-8 5 5 0 0 0-9-2 5 5 0 0 0-2 10Z',
    nube,
    niebla: nube + 'M3 20h18M6 23h12',
    llovizna: nube + 'M8 20h.01M12 22h.01M16 20h.01',
    lluvia: nube + 'M8 20l-1 3M13 20l-1 3M18 20l-1 3',
    'lluvia-helada': nube + 'M8 20v3m-1.5-1.5h3M16 20v3m-1.5-1.5h3',
    nieve: nube + 'M12 19v5m-2.2-3.8 4.4 2.6m-4.4 0 4.4-2.6',
    tormenta: nube + 'M12 16l-3 5h4l-1 3 5-6h-4l1-2',
    granizo: nube + 'M7 21h.01M18 21h.01m-6-5-3 5h4l-1 3 5-6h-4l1-2',
    'sin-dato': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM9.5 9a2.5 2.5 0 1 1 4 2L12 12v2M12 17h.01',
};

export function crearCondicionTiempo(codigo, esDia = null) {
    const {nombre, icono} = condicionTiempo(codigo, esDia);
    const elemento = document.createElement('span');
    elemento.className = 'tiempo-condicion tiempo-condicion-' + icono;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    for (const [atributo, valor] of Object.entries({viewBox: '0 0 24 24', width: '24', height: '24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.8', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true'})) svg.setAttribute(atributo, valor);
    const trazo = document.createElementNS('http://www.w3.org/2000/svg', 'path'); trazo.setAttribute('d', dibujos[icono]); svg.append(trazo);
    const texto = document.createElement('span'); texto.textContent = nombre;
    elemento.append(svg, texto);
    return elemento;
}
