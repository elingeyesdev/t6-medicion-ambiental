const formato = 'Y-m-d\\TH:i:S';

/** Conserva el valor canónico; el selector muestra la misma hora de Bolivia, sin convertirla a la zona del equipo. */
export function sincronizarCalendario(campo) {
    const selector = campo._flatpickr;
    if (!selector) return;
    const valor = campo.value;
    for (const [atributo, opcion] of [['min', 'minDate'], ['max', 'maxDate']]) {
        const limite = campo.getAttribute(atributo) || null;
        const anterior = selector.config[opcion];
        const fecha = limite ? selector.parseDate(limite, formato) : null;
        if ((anterior?.getTime() ?? null) !== (fecha?.getTime() ?? null)) selector.set(opcion, fecha);
    }
    if (valor) selector.setDate(valor, false, formato);
    else selector.clear(false);
    // Actualizar los límites nunca borra una edición inválida: la validación debe explicarla al consultar.
    campo.value = valor;
    if (valor && !selector.selectedDates.length && selector.altInput) {
        const fecha = selector.parseDate(valor, formato);
        selector.altInput.value = fecha ? selector.formatDate(fecha, 'd/m/Y H:i') : valor;
    }
    const referencia = selector.selectedDates[0] ?? selector.config.minDate ?? selector.config.maxDate;
    if (referencia) selector.jumpToDate(referencia, false);
}

export function iniciarCalendarios(...campos) {
    if (typeof globalThis.flatpickr !== 'function' || !globalThis.flatpickr.l10ns?.es) return;
    for (const campo of campos) {
        const selector = globalThis.flatpickr(campo, {
            locale: globalThis.flatpickr.l10ns.es,
            dateFormat: formato,
            altInput: true,
            altFormat: 'd/m/Y H:i',
            enableTime: true,
            time_24hr: true,
            minuteIncrement: 1,
            disableMobile: true,
            monthSelectorType: 'dropdown',
            onOpen: () => campo.dispatchEvent(new Event('focus')),
            onReady: (_, __, instancia) => instancia.calendarContainer.classList.add('geosur-calendario'),
        });
        selector.altInput.setAttribute('aria-label', campo.name === 'desde' ? 'Fecha y hora desde' : 'Fecha y hora hasta');
        selector.altInput.setAttribute('aria-describedby', 'ayuda-fechas');
        sincronizarCalendario(campo);
    }
    document.getElementById('limpiar-periodo')?.addEventListener('click', () => {
        requestAnimationFrame(() => campos[0]._flatpickr.altInput.focus());
    });
}
