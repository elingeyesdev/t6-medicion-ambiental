/** Hora del servidor más tiempo transcurrido; no depende del reloj civil del navegador. */
export function crearRelojServidor(fecha, monotono = () => performance.now()) {
    let base, referencia;
    function sincronizar(valor) {
        if (typeof valor !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/.test(valor) || !Number.isFinite(Date.parse(valor))) {
            throw new Error('No se pudo comprobar la hora. Reintenta la consulta.');
        }
        base = Date.parse(valor);
        referencia = monotono();
    }
    sincronizar(fecha);
    return { sincronizar, ahora: () => base + Math.max(0, monotono() - referencia) };
}
