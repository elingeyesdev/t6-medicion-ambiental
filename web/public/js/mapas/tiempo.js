import { presentacion } from '../datos/unidades.js';

function nodo(tipo, texto, clase) {
    const n = document.createElement(tipo);
    if (texto !== undefined) n.textContent = texto;
    if (clase) n.className = clase;
    return n;
}

export function crearConsultaTiempo({url, latitud, longitud, automatico = false}) {
    const elemento = nodo('section', undefined, 'mon-tiempo');
    const titulo = nodo('h3', 'Tiempo y pronóstico'); titulo.hidden = true;
    const boton = nodo('button', 'Tiempo y pronóstico', 'btn btn-outline-primary');
    boton.type = 'button';
    const estado = nodo('p'); estado.setAttribute('role', 'status');
    const resultado = nodo('div');
    elemento.append(titulo, boton, estado, resultado);
    let peticion = null;
    let ultimo = null;
    let variableElegida = 'temperatura';
    function dibujar(datos) {
        const abierta = resultado.querySelector('details')?.open ?? false;
        resultado.replaceChildren();
        const fecha = new Intl.DateTimeFormat('es-BO', {timeZone: datos.zonaHoraria, dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23'});
        const valor = (v, clave) => presentacion.texto(clave, v, datos.variables[clave]?.unidad ?? '');
        estado.textContent = 'Datos estimados · ' + fecha.format(new Date(datos.actual.instante)) + ' · Hora del lugar';
        const tarjetas = nodo('div', undefined, 'tiempo-valores');
        for (const clave of ['temperatura', 'sensacion', 'humedad', 'probabilidad', 'viento', 'visibilidad']) {
            const tarjeta = nodo('div', undefined, 'tiempo-valor tiempo-' + clave);
            tarjeta.append(nodo('span', datos.variables[clave].nombre), nodo('strong', valor(datos.actual[clave], clave)));
            tarjetas.append(tarjeta);
        }
        resultado.append(tarjetas);
        const tabla = nodo('table', undefined, 'table tiempo-diario');
        tabla.append(nodo('caption', 'Temperaturas en ' + presentacion.unidad('temperatura', '°C') + ' · Lluvia en mm'));
        const cabecera = nodo('thead'); const filaCabecera = nodo('tr');
        for (const t of ['Día', 'Máxima', 'Mínima', 'Lluvia']) filaCabecera.append(nodo('th', t));
        cabecera.append(filaCabecera); tabla.append(cabecera);
        const cuerpo = nodo('tbody');
        const dia = new Intl.DateTimeFormat('es-BO', {timeZone: 'UTC', day: 'numeric', month: 'short'});
        for (const d of datos.dias) {
            const fila = nodo('tr');
            for (const t of [dia.format(new Date(d.fecha + 'T12:00:00Z')), valor(d.maxima, 'temperatura'), valor(d.minima, 'temperatura'), valor(d.lluvia, 'lluvia')]) fila.append(nodo('td', t));
            cuerpo.append(fila);
        }
        tabla.append(cuerpo);
        const diaria = nodo('div', undefined, 'table-responsive'); diaria.append(tabla);
        resultado.append(nodo('h3', 'Próximos días'), diaria);
        const porHora = nodo('details'); porHora.open = abierta; porHora.append(nodo('summary', 'Pronóstico por hora'));
        const etiqueta = nodo('label', 'Variable'); const selector = nodo('select', undefined, 'form-select');
        for (const [clave, variable] of Object.entries(datos.variables)) {
            const unidad = presentacion.unidad(clave, variable.unidad);
            selector.add(new Option(variable.nombre + (unidad ? ' (' + unidad + ')' : ''), clave));
        }
        selector.value = variableElegida;
        etiqueta.append(selector);
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 540 210'); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', 'Evolución prevista de la variable elegida');
        const escala = nodo('p');
        const tablaHoras = nodo('table', undefined, 'table');
        const cabHoras = nodo('thead'); const titHoras = nodo('tr'); titHoras.append(nodo('th', 'Fecha y hora'), nodo('th', 'Valor')); cabHoras.append(titHoras);
        const filasHoras = nodo('tbody'); tablaHoras.append(cabHoras, filasHoras);
        const desplazable = nodo('div', undefined, 'tiempo-horas table-responsive'); desplazable.append(tablaHoras);
        function grafico() {
            const clave = selector.value; variableElegida = clave;
            const unidad = datos.variables[clave].unidad;
            const serie = datos.horas.map(f => ({instante: f.instante, valor: presentacion.numero(clave, f[clave], unidad)}));
            const validos = serie.map(f => f.valor).filter(v => v !== null);
            svg.replaceChildren(); filasHoras.replaceChildren();
            if (validos.length) {
                const min = Math.min(...validos), max = Math.max(...validos);
                const formato = v => new Intl.NumberFormat('es-BO', {maximumFractionDigits: 2}).format(v) + ' ' + presentacion.unidad(clave, unidad);
                escala.textContent = 'Mínimo ' + formato(min) + ' · Máximo ' + formato(max);
                let segmento = [];
                const trazar = () => {
                    if (!segmento.length) return;
                    const linea = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    linea.setAttribute('points', segmento.join(' ')); linea.setAttribute('fill', 'none'); linea.setAttribute('stroke', '#167645'); linea.setAttribute('stroke-width', '3'); svg.append(linea); segmento = [];
                };
                const inicio = Date.parse(serie[0].instante), fin = Date.parse(serie.at(-1).instante);
                for (const f of serie) {
                    if (f.valor === null) { trazar(); continue; }
                    segmento.push((20 + (Date.parse(f.instante) - inicio) / Math.max(1, fin - inicio) * 500) + ',' + (185 - (f.valor - min) / Math.max(1, max - min) * 160));
                }
                trazar();
            } else escala.textContent = 'No hay datos disponibles para esta variable.';
            for (const f of datos.horas) { const fila = nodo('tr'); fila.append(nodo('td', fecha.format(new Date(f.instante))), nodo('td', valor(f[clave], clave))); filasHoras.append(fila); }
        }
        selector.addEventListener('change', grafico); grafico();
        porHora.append(etiqueta, svg, escala, desplazable);
        const extra = nodo('details'); extra.append(nodo('summary', 'Otros datos del tiempo'));
        for (const clave of ['lluvia', 'nieve', 'nubosidad', 'presion', 'direccion', 'uv']) extra.append(nodo('p', datos.variables[clave].nombre + ': ' + valor(datos.actual[clave], clave)));
        const fuente = nodo('a', 'Fuente: Open-Meteo'); fuente.href = 'https://open-meteo.com/'; fuente.target = '_blank'; fuente.rel = 'noopener noreferrer';
        resultado.append(porHora, extra, fuente);
        titulo.hidden = automatico; boton.textContent = 'Actualizar tiempo';
    }
    const actualizar = () => { if (ultimo) dibujar(ultimo); };
    document.addEventListener('unidades-cambiadas', actualizar);

    async function consultar() {
        peticion?.abort(); peticion = new AbortController();
        boton.disabled = true; ultimo = null; estado.textContent = 'Cargando…'; resultado.replaceChildren();
        try {
            const destino = new URL(url, location.origin);
            destino.searchParams.set('latitud', latitud); destino.searchParams.set('longitud', longitud);
            const r = await fetch(destino, {headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: peticion.signal});
            const datos = await r.json();
            if (!r.ok) throw new Error(datos.error?.mensaje ?? 'No se pudo consultar el tiempo.');
            if (!datos.actual || !Array.isArray(datos.dias) || !Array.isArray(datos.horas)) throw new Error('No se pudo leer el pronóstico.');
            ultimo = datos; dibujar(datos);
        } catch (e) {
            if (e.name !== 'AbortError') estado.textContent = e instanceof TypeError || e instanceof SyntaxError ? 'No se pudo conectar. Inténtalo de nuevo.' : e.message;
        } finally { boton.disabled = false; }
    }
    boton.addEventListener('click', consultar);
    if (automatico) consultar();
    return {elemento, cancelar: () => { peticion?.abort(); document.removeEventListener('unidades-cambiadas', actualizar); }};
}
