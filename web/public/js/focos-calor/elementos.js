import { fechaBolivia } from './contrato.js';
import { nombreCortoMunicipio } from './representacion.js';

// Silueta derivada del icono flame de Tabler, licencia MIT en vendor/tabler-icons/.
// Solo SVG fijo de la aplicación: ningún texto de la respuesta se inserta como HTML.
const llama = '<svg viewBox="0 0 24 24" aria-hidden="true"><path class="llama-exterior" d="M12 10.941c2.333 -3.308 .167 -7.823 -1 -8.941c0 3.395 -2.235 5.299 -3.667 6.706c-1.43 1.408 -2.333 3.294 -2.333 5.588c0 3.704 3.134 6.706 7 6.706c3.866 0 7 -3.002 7 -6.706c0 -1.712 -1.232 -4.403 -2.333 -5.588c-2.084 3.353 -3.257 3.353 -4.667 2.235Z"/><path class="llama-interior" d="M12 13c.6 2-2 2.5-2 4.3a2.5 2.5 0 0 0 5 0c0-1.5-1.2-3.1-3-4.3Z"/></svg>';

export function crearSimboloCalor(cantidad = null) {
    const simbolo = document.createElement('span');
    simbolo.className = 'simbolo-calor';
    simbolo.innerHTML = llama;
    if (cantidad !== null) {
        const numero = document.createElement('strong'); numero.textContent = String(cantidad);
        simbolo.append(numero);
    }
    return simbolo;
}

export function crearContenidoFoco(foco, municipio) {
    const nodo = (tipo, texto, clase) => {
        const e = document.createElement(tipo); e.textContent = texto; e.className = clase; return e;
    };
    const confianza = ['alta', 'nominal', 'baja'].includes(foco.atributos.confianza) ? foco.atributos.confianza : 'desconocida';
    const etiquetas = { alta: 'Alta', nominal: 'Nominal', baja: 'Baja', desconocida: 'Sin clasificar' };
    const insignia = nodo('span', etiquetas[confianza], `confianza-foco confianza-${confianza}`);
    insignia.title = `Confianza ${etiquetas[confianza].toLowerCase()}`;
    return [
        nodo('strong', nombreCortoMunicipio(municipio), 'municipio-foco'),
        nodo('span', fechaBolivia(foco.fechas.observadoDesde), 'fecha-foco'),
        insignia,
        nodo('span', 'Ver detalle →', 'accion-foco'),
    ];
}
