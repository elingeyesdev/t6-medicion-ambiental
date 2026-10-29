// Los formularios muestran Bolivia (UTC-4); la API recibe instantes UTC explícitos.
export function instanteBolivia(texto) {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(texto)) throw new Error('Revisa las fechas elegidas.');
    const instante = Date.parse(texto + '-04:00');
    if (!Number.isFinite(instante) || new Date(instante - 4 * 3600000).toISOString().slice(0, 16) !== texto) {
        throw new Error('Revisa las fechas elegidas.');
    }
    return instante;
}

export function consultaMonitoreo(valores, ahora = Date.now()) {
    const desde = instanteBolivia(valores.desde);
    const hasta = instanteBolivia(valores.hasta);
    if (desde >= hasta || hasta > ahora || hasta - desde > 7 * 86400000) {
        throw new Error('Elige hasta siete días, sin fechas futuras.');
    }
    const consulta = new URLSearchParams({
        metrica: valores.metrica,
        desde: new Date(desde).toISOString().replace('.000Z', 'Z'),
        hasta: new Date(hasta).toISOString().replace('.000Z', 'Z'),
    });
    if (valores.por_momento === 'on') {
        const momento = instanteBolivia(valores.momento);
        if (momento < desde || momento >= hasta || momento > ahora) throw new Error('Elige un momento dentro del período consultado.');
        consulta.set('momento', new Date(momento).toISOString().replace('.000Z', 'Z'));
    }
    for (const campo of ['pais', 'region', 'origen']) {
        if (valores[campo]) consulta.set(campo, valores[campo]);
    }
    return consulta;
}
