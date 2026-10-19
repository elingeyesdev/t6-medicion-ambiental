import { aLocal, aUtc } from './contrato.js';

const semana = 7 * 86400000;
const instante = valor => { try { return new Date(aUtc(valor)).getTime(); } catch { return null; } };

export function limitesPeriodo(desde, hasta) {
    const inicio = instante(desde), fin = instante(hasta);
    return {
        desde: fin === null ? { min: '', max: '' } : { min: aLocal(fin - semana), max: aLocal(fin - 1000) },
        hasta: inicio === null ? { min: '', max: '' } : { min: aLocal(inicio + 1000), max: aLocal(inicio + semana) },
    };
}

export function ajustarCalendarios(desde, hasta) {
    const limites = limitesPeriodo(desde.value, hasta.value);
    for (const [campo, reglas] of [[desde, limites.desde], [hasta, limites.hasta]]) {
        for (const atributo of ['min', 'max']) {
            if (reglas[atributo]) campo.setAttribute(atributo, reglas[atributo]);
            else campo.removeAttribute(atributo);
        }
    }
}
