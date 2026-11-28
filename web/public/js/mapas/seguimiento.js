import { iniciarMapaBase } from './base.js';

const contenedor = document.querySelector('[data-mapa-zona]');
const L = window.L;
let mostrarFoco;


if (contenedor && L) {
    const editable = contenedor.hasAttribute('data-editar');
    const formulario = contenedor.closest('form');
    const campos = formulario?.elements;
    const mapa = L.map(contenedor).setView([-17.8, -62.6], 7);
    if (!iniciarMapaBase(mapa, () => {}, null)) {
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(mapa);
    }
    const circulo = L.circle([0, 0], { color: '#315c48', fillOpacity: .18 });
    const centro = L.circleMarker([0, 0], { radius: 6, color: '#fff', fillColor: '#274a3c', fillOpacity: 1 });
    if (!editable) {
        const enlace = document.querySelector('[data-foco-en-mapa]');
        const leyenda = document.querySelector('[data-leyenda-foco]');
        const foco = L.circleMarker([0, 0], { radius: 8, color: '#fff', weight: 2, fillColor: '#a45330', fillOpacity: 1 });
        foco.bindTooltip('Foco de calor más cercano');
        mostrarFoco = (datos) => {
            const disponible = Number.isFinite(datos?.latitud) && Number.isFinite(datos?.longitud);
            enlace.hidden = !disponible;
            leyenda.hidden = !disponible;
            if (disponible) foco.setLatLng([datos.latitud, datos.longitud]).addTo(mapa);
            else foco.remove();
        };
        enlace.addEventListener('click', () => {
            if (!mapa.hasLayer(foco)) return;
            mapa.setView(foco.getLatLng(), Math.max(mapa.getZoom(), 13), { animate: false });
            mapa.recordarParaCentrar?.(foco.getLatLng());
            foco.openTooltip();
        });
    }
    const dibujar = (encuadrar = false) => {
        const latitud = editable ? campos.latitud.valueAsNumber : Number(contenedor.dataset.latitud);
        const longitud = editable ? campos.longitud.valueAsNumber : Number(contenedor.dataset.longitud);
        const radio = editable ? campos.radio_km.valueAsNumber : Number(contenedor.dataset.radio);
        if (!(Math.abs(latitud) <= 90 && Math.abs(longitud) <= 180 && radio >= 1 && radio <= 50)) {
            circulo.remove();
            centro.remove();
            return;
        }
        circulo.setLatLng([latitud, longitud]).setRadius(radio * 1000).addTo(mapa);
        centro.setLatLng([latitud, longitud]).addTo(mapa);
        mapa.recordarParaCentrar?.([latitud, longitud]);
        if (encuadrar) mapa.fitBounds(circulo.getBounds(), { padding: [18, 18], maxZoom: 13 });
    };
    if (editable) {
        let enviando = false;
        const botonEvaluar = formulario.querySelector('[name="evaluar"]');
        const textoEvaluar = botonEvaluar?.textContent;
        formulario.addEventListener('submit', (evento) => {
            if (enviando) {
                evento.preventDefault();
                return;
            }
            enviando = true;
            if (evento.submitter?.name === 'evaluar') evento.submitter.textContent = 'Guardando y evaluando…';
        });
        window.addEventListener('pageshow', ({ persisted }) => {
            if (persisted) {
                enviando = false;
                if (botonEvaluar) botonEvaluar.textContent = textoEvaluar;
            }
        });
        campos.radio_km.addEventListener('change', () => dibujar(true));
        mapa.on('click', ({ latlng }) => {
            campos.latitud.value = latlng.lat.toFixed(5);
            campos.longitud.value = latlng.lng.toFixed(5);
            dibujar();
        });
        formulario.addEventListener('input', (evento) => {
            if (['latitud', 'longitud', 'radio_km'].includes(evento.target.name)) dibujar(evento.target.name !== 'radio_km');
        });
    }
    dibujar(true);
}

// La vigilancia actual se muestra por separado de la evaluación histórica guardada.
const aviso = document.querySelector('[data-seguimiento-en-vivo]');
if (aviso) {
    const formulario = document.querySelector('[data-evaluar-zona]');
    const boton = document.querySelector('[data-actualizar-zona]');
    const estado = aviso.querySelector('[data-estado-zona]');
    const titulo = aviso.querySelector('[data-titulo-zona]');
    const info = aviso.querySelector('[data-info-zona]');
    const origen = aviso.querySelector('[data-origen-zona]');
    const consulta = aviso.querySelector('[data-dato-consulta] dt');
    const frecuencia = 10 * 60 * 1000;
    let siguiente = Date.now() + (Number(aviso.dataset.espera) || 0);
    let consultando = false;

    function dato(atributo, valor) {
        const elemento = aviso.querySelector(atributo);
        elemento.hidden = !valor;
        elemento.querySelector('dd').textContent = valor || '';
    }

    function presentar(resultado) {
        const detecciones = resultado.detecciones;
        const conFocos = detecciones > 0;
        if (detecciones === null) {
            titulo.textContent = 'Sin cobertura para esta consulta';
            aviso.dataset.estado = 'indeterminado';
        } else if (detecciones === 0) {
            titulo.textContent = 'Sin focos en los datos consultados';
            aviso.dataset.estado = 'sin-detecciones';
        } else {
            titulo.textContent = detecciones === 1 ? 'Un foco de calor dentro del radio' : detecciones + ' focos de calor dentro del radio';
            aviso.dataset.estado = 'deteccion';
        }

        info.textContent = conFocos
            ? 'La ubicación es aproximada y requiere verificación en campo.'
            : 'La cobertura satelital depende del período y de los datos disponibles.';
        dato('[data-dato-distancia]', conFocos && resultado.distancia ? resultado.distancia + ' km' : null);
        dato('[data-dato-deteccion]', conFocos ? resultado.foco?.hora : null);
        consulta.textContent = resultado.consultadoEn ? 'Última consulta' : 'Período consultado hasta';
        dato('[data-dato-consulta]', resultado.consultadoEn || resultado.hasta);
        origen.textContent = [resultado.fuente, resultado.producto, 'Hora de Bolivia', resultado.cobertura === 'parcial' && 'Cobertura parcial']
            .filter(Boolean).join(' · ');
        origen.hidden = false;
        mostrarFoco?.(conFocos ? resultado.foco : null);
    }

    async function revisarZona(forzar = false) {
        if (consultando || (!forzar && (document.hidden || Date.now() < siguiente))) return;
        siguiente = Date.now() + frecuencia;
        consultando = true;
        boton.disabled = true;
        estado.textContent = 'Buscando detecciones recientes…';
        estado.hidden = false;
        try {
            const datos = new FormData();
            datos.set('_token', formulario.elements._token.value);
            datos.set('en_vivo', '1');
            const respuesta = await fetch(formulario.action, {
                method: 'POST', body: datos, headers: { Accept: 'application/json' },
            });
            if (!respuesta.ok) throw new Error('Consulta sin respuesta');
            presentar(await respuesta.json());
            estado.hidden = true;
        } catch {
            estado.textContent = 'No se pudo actualizar. Puedes volver a intentarlo con «Actualizar ahora».';
        } finally {
            boton.disabled = false;
            consultando = false;
        }
    }

    const inicial = JSON.parse(aviso.dataset.resumen);
    if (inicial) presentar(inicial);
    boton.hidden = false;
    boton.addEventListener('click', () => revisarZona(true));
    revisarZona();
    window.setInterval(revisarZona, frecuencia);
    document.addEventListener('visibilitychange', () => revisarZona());
}
