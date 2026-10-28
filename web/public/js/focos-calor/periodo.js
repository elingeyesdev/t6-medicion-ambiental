import { aLocal, aUtc } from './contrato.js';

const semana = 7 * 86400000;
const instante = valor => { try { return new Date(aUtc(valor)).getTime(); } catch { return null; } };

export function periodoReciente(dias = 7, ahora = Date.now()) {
    const fin = Math.floor(ahora / 1000) * 1000;
    return { desde: new Date(fin - dias * 86400000).toISOString(), hasta: new Date(fin).toISOString() };
}

export function limitesPeriodo(desde, hasta, ahora = Date.now()) {
    const inicio = instante(desde), fin = instante(hasta);
    const presente = Math.floor(ahora / 1000) * 1000;
    return {
        desde: fin === null ? { min: '', max: aLocal(presente) } : { min: aLocal(fin - semana), max: aLocal(Math.min(fin - 1000, presente)) },
        hasta: inicio === null ? { min: '', max: aLocal(presente) } : { min: aLocal(inicio + 1000), max: aLocal(Math.min(inicio + semana, presente)) },
    };
}

export function ajustarCalendarios(desde, hasta, ahora = Date.now()) {
    const limites = limitesPeriodo(desde.value, hasta.value, ahora);
    for (const [campo, reglas] of [[desde, limites.desde], [hasta, limites.hasta]]) {
        for (const atributo of ['min', 'max']) {
            if (reglas[atributo]) campo.setAttribute(atributo, reglas[atributo]);
            else campo.removeAttribute(atributo);
        }
    }
}
