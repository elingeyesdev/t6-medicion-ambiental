const bandas = [
    [20, 'Buena', 'buena'], [40, 'Razonable', 'razonable'], [60, 'Moderada', 'moderada'],
    [80, 'Desfavorable', 'desfavorable'], [100, 'Muy desfavorable', 'muy-desfavorable'],
    [Infinity, 'Extremadamente desfavorable', 'extrema'],
];

export function categoriaEuropea(valor) {
    if (typeof valor !== 'number' || !Number.isFinite(valor) || valor < 0) return {nombre: 'Sin dato', clase: 'sin-dato'};
    const [, nombre, clase] = bandas.find(([limite]) => valor <= limite);
    return {nombre, clase};
}

// Un lugar cerrado o reemplazado no recibe resultados de una petición anterior.
export function cargarAire({consultar, mostrar, fallar, cargando}) {
    let secuencia = 0, peticion;
    return {
        async cargar() {
            const turno = ++secuencia;
            peticion?.abort();
            const actual = new AbortController(); peticion = actual;
            cargando(true);
            try {
                const datos = await consultar(actual.signal);
                if (turno === secuencia) mostrar(datos);
            } catch (error) {
                if (turno === secuencia && error.name !== 'AbortError') fallar();
            } finally {
                if (turno === secuencia) cargando(false);
            }
        },
        cancelar() { secuencia++; peticion?.abort(); cargando(false); },
    };
}

function nodo(tipo, texto, clase) {
    const elemento = document.createElement(tipo);
    if (texto !== undefined) elemento.textContent = texto;
    if (clase) elemento.className = clase;
    return elemento;
}

export function crearConsultaAire({url, latitud, longitud}) {
    const elemento = nodo('section', undefined, 'mon-aire');
    const titulo = nodo('h3', 'Calidad del aire actual'); titulo.hidden = true;
    const boton = nodo('button', 'Calidad del aire', 'btn btn-outline-primary'); boton.type = 'button';
    const estado = nodo('p'); estado.setAttribute('role', 'status');
    const resultado = nodo('div');
    elemento.append(titulo, boton, estado, resultado);
    const fecha = new Intl.DateTimeFormat('es-BO', {timeZone: 'America/La_Paz', dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23'});
    const numero = valor => typeof valor === 'number' && Number.isFinite(valor)
        ? new Intl.NumberFormat('es-BO', {maximumFractionDigits: 1}).format(valor) : 'Sin dato';
    let ultimo = null;
    function dibujar(datos) {
        const abiertas = resultado.querySelector('details')?.open ?? false;
        resultado.replaceChildren();
        const categoria = categoriaEuropea(datos.actual.indice);
        const tarjeta = nodo('div', undefined, 'aire-indice aire-' + categoria.clase);
        tarjeta.append(nodo('span', 'Índice europeo'), nodo('strong', numero(datos.actual.indice)), nodo('span', categoria.nombre));
        resultado.append(tarjeta, nodo('p', fecha.format(new Date(datos.actual.instante)) + ' · Bolivia'),
            nodo('p', 'Estimación actual del modelo para esta zona.'));
        const particulas = nodo('div', undefined, 'aire-particulas');
        for (const clave of ['pm25', 'pm10']) {
            const valor = nodo('div');
            valor.append(nodo('span', datos.variables[clave].nombre), nodo('strong', numero(datos.actual[clave]) + (datos.actual[clave] === null ? '' : ' μg/m³')));
            particulas.append(valor);
        }
        resultado.append(particulas);
        const horas = nodo('details'); horas.open = abiertas; horas.append(nodo('summary', 'Últimas 24 horas'));
        const tabla = nodo('table', undefined, 'table');
        tabla.append(nodo('caption', 'Índice europeo · hora de Bolivia'));
        const cabecera = nodo('thead'), titulos = nodo('tr');
        for (const texto of ['Fecha y hora', 'Índice', 'Estado']) { const th = nodo('th', texto); th.scope = 'col'; titulos.append(th); }
        cabecera.append(titulos); tabla.append(cabecera);
        const cuerpo = nodo('tbody');
        for (const hora of datos.horas) {
            const fila = nodo('tr');
            fila.append(nodo('td', fecha.format(new Date(hora.instante))), nodo('td', numero(hora.indice)), nodo('td', categoriaEuropea(hora.indice).nombre));
            cuerpo.append(fila);
        }
        tabla.append(cuerpo);
        const desplazable = nodo('div', undefined, 'aire-horas table-responsive'); desplazable.append(tabla); horas.append(desplazable);
        const otros = nodo('details'); otros.append(nodo('summary', 'Otros contaminantes'));
        for (const clave of ['no2', 'ozono', 'so2']) otros.append(nodo('p', datos.variables[clave].nombre + ': ' + numero(datos.actual[clave]) + (datos.actual[clave] === null ? '' : ' μg/m³')));
        const informacion = nodo('details'); informacion.append(nodo('summary', 'Sobre estos datos'),
            nodo('p', 'El índice resume la calidad del aire según la escala europea. Un valor mayor indica peor calidad; no es la escala de Estados Unidos.'),
            nodo('p', 'Las partículas y gases se expresan en microgramos por metro cúbico. La estimación representa una zona de unos 45 km, no una estación en este punto.'));
        const fuente = nodo('a', 'CAMS y Open-Meteo · CC BY 4.0'); fuente.href = 'https://open-meteo.com/en/docs/air-quality-api'; fuente.target = '_blank'; fuente.rel = 'noopener noreferrer';
        informacion.append(fuente);
        resultado.append(horas, otros, informacion);
        titulo.hidden = false; boton.textContent = 'Actualizar aire'; estado.textContent = '';
    }
    const carga = cargarAire({
        async consultar(signal) {
            const destino = new URL(url, location.origin);
            destino.searchParams.set('latitud', latitud); destino.searchParams.set('longitud', longitud);
            const respuesta = await fetch(destino, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store', signal});
            const datos = await respuesta.json();
            if (!respuesta.ok || datos.escala !== 'european_aqi' || !datos.actual || !Array.isArray(datos.horas) || !datos.variables) throw new Error('No disponible');
            return datos;
        },
        mostrar(datos) { ultimo = datos; dibujar(datos); },
        fallar() {
            estado.textContent = ultimo ? 'No se pudo actualizar. Se conserva la consulta anterior.' : 'No pudimos cargar la calidad del aire. Inténtalo de nuevo.';
            boton.textContent = 'Volver a intentar';
        },
        cargando(activa) {
            boton.disabled = activa;
            if (activa) estado.textContent = 'Cargando…';
            else if (estado.textContent === 'Cargando…') estado.textContent = '';
        },
    });
    boton.addEventListener('click', () => carga.cargar());
    return {elemento, cancelar: () => carga.cancelar()};
}
