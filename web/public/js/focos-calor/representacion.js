import { fechaBolivia } from './contrato.js';

export function etiquetaDeteccion(foco, nombreMunicipio) {
    return `${fechaBolivia(foco.fechas.observadoDesde)} · ${nombreMunicipio || 'Municipio no determinado'} · confianza ${foco.atributos.confianza}`;
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

// La denominación oficial completa se conserva en el detalle y en la API.
export function nombreCortoMunicipio(nombre) {
    return typeof nombre === 'string' && nombre.trim() ? nombre.split(' (')[0].trim() : 'Municipio no determinado';
}

export function etiquetaBreveDeteccion(foco, municipio) {
    return `${nombreCortoMunicipio(municipio)} · ${fechaBolivia(foco.fechas.observadoDesde)}`;
}
