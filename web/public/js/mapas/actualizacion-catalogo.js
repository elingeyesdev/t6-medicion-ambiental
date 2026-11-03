/** Conserva las imágenes elegidas, incluso si ya pasaron al archivo del servidor. */
export function conservarComparacion(anterior, nuevo, seleccion) {
    return nuevo.map(capa => {
        const cuadros = [...capa.cuadros];
        for (const elegido of seleccion.filter(s => s.capa === capa.id && s.fecha)) {
            if (cuadros.some(c => c.id === elegido.fecha)) continue;
            const guardado = anterior.find(c => c.id === capa.id)?.cuadros.find(c => c.id === elegido.fecha);
            if (guardado) cuadros.push(guardado);
        }
        return {...capa, cuadros};
    });
}

/** Solo detecta novedades. Nunca inicia descargas del proveedor ni cambia la selección. */
export function hayFechasNuevas(anterior, nuevo) {
    const conocidas = new Set(anterior.flatMap(capa => capa.cuadros.map(c => c.id)));
    return nuevo.some(capa => capa.cuadros.some(c => !conocidas.has(c.id)));
}

export function vigilarCatalogo({consultar, activa, recibir, programar = setInterval, cancelar = clearInterval}) {
    let ocupado = false, detenido = false, peticion, generacion = 0;
    async function comprobar() {
        if (detenido || ocupado || !activa()) return;
        ocupado = true;
        const turno = generacion;
        peticion = new AbortController();
        try {
            const datos = await consultar(peticion.signal);
            if (!detenido && turno === generacion) recibir(datos);
        } catch { /* La comparación sigue disponible con su catálogo anterior. */ }
        finally { ocupado = false; }
    }
    let reloj = programar(comprobar, 60000);
    return {comprobar,
        detener() { detenido = true; generacion++; cancelar(reloj); peticion?.abort(); },
        reanudar() { if (detenido) { detenido = false; reloj = programar(comprobar, 60000); } },
    };
}
