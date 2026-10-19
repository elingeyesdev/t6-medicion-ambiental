import { fechaBolivia, numero } from './contrato.js';

export function etiquetaDeteccion(foco, nombreMunicipio) {
    return `${fechaBolivia(foco.fechas.observadoDesde)} · ${nombreMunicipio || 'Municipio no determinado'} · confianza ${foco.atributos.confianza} · FRP ${numero(foco.atributos.potenciaRadiativa, 'MW')}`;
}

/** Grupos de proximidad visual de UNA página; no alteran coordenadas ni métricas. */
export function agruparProximos(entradas, distancia = 28) {
    const pendientes = new Set(entradas);
    const grupos = [];
    while (pendientes.size) {
        const primero = pendientes.values().next().value;
        pendientes.delete(primero);
        const grupo = [primero];
        for (let i = 0; i < grupo.length; i++) {
            for (const candidato of pendientes) {
                if (Math.hypot(grupo[i].punto.x - candidato.punto.x, grupo[i].punto.y - candidato.punto.y) <= distancia) {
                    pendientes.delete(candidato);
                    grupo.push(candidato);
                }
            }
        }
        grupos.push(grupo);
    }
    return grupos;
}
