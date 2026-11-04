import { iniciarMapaBase } from '../../js/mapas/base.js';

const formulario = document.querySelector('[data-enviro-registro]');
if (formulario) {
    const extras = formulario.querySelector('[data-registro-extras]');
    const latitud = formulario.elements.latitud;
    const longitud = formulario.elements.longitud;
    const estado = document.getElementById('estado-ubicacion');
    const limpiar = document.getElementById('quitar-ubicacion');
    const aviso = formulario.querySelector('[data-notif-help]');
    let mapa, marcador;
    function actualizar() {
        const completa = latitud.value !== '' && longitud.value !== '' && latitud.checkValidity() && longitud.checkValidity();
        estado.textContent = completa ? 'Ubicación seleccionada.' : 'Sin ubicación seleccionada.';
        limpiar.hidden = !completa;
        if (marcador) { marcador.remove(); marcador = null; }
        if (mapa && completa) marcador = window.L.marker([Number(latitud.value), Number(longitud.value)]).addTo(mapa);
        let seleccionados = false;
        for (const canal of formulario.querySelectorAll('[data-canal]')) {
            canal.disabled = !completa || canal.dataset.disponible !== '1';
            if (canal.disabled) canal.checked = false;
            seleccionados ||= canal.checked;
            const destino = formulario.querySelector(`[data-destino="${canal.dataset.canal}"]`);
            if (destino) {
                destino.hidden = !canal.checked;
                const campo = destino.querySelector('input');
                campo.disabled = !canal.checked;
                campo.required = canal.checked;
            }
        }
        const consentimiento = formulario.querySelector('[data-consentimiento]');
        consentimiento.hidden = !seleccionados;
        const aceptar = consentimiento.querySelector('input');
        aceptar.disabled = !seleccionados;
        aceptar.required = seleccionados;
        if (!seleccionados) aceptar.checked = false;
        aviso.textContent = completa ? 'Elige los canales disponibles y confirma que deseas recibir avisos.' : 'Selecciona tu ubicación para habilitar los canales disponibles. Los avisos requieren tu consentimiento.';
    }
    extras.disabled = false;
    if (window.L) {
        mapa = window.L.map('registro-mapa', {scrollWheelZoom: false}).setView([-17.78, -63.18], 5);
        iniciarMapaBase(mapa, () => { estado.textContent = 'Puedes elegir tu ubicación usando las coordenadas.'; });
        mapa.on('click', evento => {
            latitud.value = evento.latlng.lat.toFixed(6);
            longitud.value = evento.latlng.lng.toFixed(6);
            actualizar();
        });
        new ResizeObserver(() => mapa.invalidateSize({animate: false})).observe(document.getElementById('registro-mapa'));
    }
    for (const campo of [latitud, longitud]) campo.addEventListener('input', actualizar);
    for (const campo of formulario.querySelectorAll('[data-canal]')) campo.addEventListener('change', actualizar);
    limpiar.addEventListener('click', () => { latitud.value = ''; longitud.value = ''; actualizar(); });
    actualizar();
}
