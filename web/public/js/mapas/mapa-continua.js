import { medida, presentacion } from '../datos/unidades.js';

export function puntosContinuos(estado, consulta, variable) {
    if (estado?.origen !== 'simulado' || !Array.isArray(estado.puntos) || !Array.isArray(consulta?.datos)) return [];
    const permitidos = new Set(consulta.datos.map(p => p.codigo));
    return estado.puntos.filter(p => permitidos.has(p.codigo) && typeof p.nombre === 'string'
        && Number.isFinite(p.latitud) && Math.abs(p.latitud) <= 90
        && Number.isFinite(p.longitud) && Math.abs(p.longitud) <= 180).map(p => ({
        codigo: p.codigo, nombre: p.nombre, latitud: p.latitud, longitud: p.longitud,
        valor: typeof p.valores?.[variable] === 'number' && Number.isFinite(p.valores[variable]) ? p.valores[variable] : null,
    }));
}

export function iniciarMapaContinua(mapa, L, configuracion, consultaActual) {
    const raiz = document.querySelector('[data-simulacion-continua][data-modo="compacto"]');
    if (!raiz) return;
    const mostrar = raiz.querySelector('[data-mostrar-continua]');
    const variable = raiz.querySelector('[data-variable-continua]');
    const texto = raiz.querySelector('[data-estado-texto]');
    const resumen = raiz.querySelector('[data-resumen-continua]');
    const capa = L.layerGroup().addTo(mapa);
    let estado = null, elegido = null, temporizador = null, peticion = null, secuencia = 0, cerrada = false;
    const nombres = { sin_preparar: 'Sin recorrido preparado', lista: 'Lista para iniciar', activa: 'En marcha', pausada: 'En pausa', detenida: 'Detenida' };
    const fecha = new Intl.DateTimeFormat('es-BO', {dateStyle: 'short', timeStyle: 'short', timeZone: 'America/La_Paz'});
    function dibujar() {
        capa.clearLayers();
        if (!mostrar.checked || !estado) return;
        const metrica = variable.value, unidad = configuracion.metricas[metrica]?.unidad ?? '';
        const puntos = puntosContinuos(estado, consultaActual(), metrica);
        resumen.textContent = `${puntos.length} puntos simulados en la selección consultada.`;
        for (const p of puntos) {
            const cifra = presentacion.texto(metrica, p.valor, unidad);
            const icono = L.divIcon({className: 'continua-marcador', html: '<span aria-hidden="true">S</span>', iconSize: [32, 32], iconAnchor: [16, 16]});
            const marcador = L.marker([p.latitud, p.longitud], {icon: icono, title: `${p.nombre} · Simulado · ${cifra}`}).addTo(capa);
            const ficha = document.createElement('section');
            const nombre = document.createElement('strong'); nombre.textContent = p.nombre; ficha.append(nombre);
            const origen = document.createElement('p'); origen.textContent = 'Simulado'; ficha.append(origen);
            const valor = document.createElement('p'); valor.append(medida(metrica, p.valor, unidad)); ficha.append(valor);
            if (estado.instante && Number.isFinite(Date.parse(estado.instante))) {
                const tiempo = document.createElement('p'); tiempo.textContent = fecha.format(new Date(estado.instante)) + ' · Bolivia'; ficha.append(tiempo);
            }
            marcador.bindPopup(ficha, {autoPan: false});
            marcador.on('click', () => { elegido = p.codigo; mapa.panTo([p.latitud, p.longitud], {animate: false}); marcador.openPopup(); });
            if (elegido === p.codigo) marcador.openPopup();
        }
    }
    function cancelar() { clearTimeout(temporizador); peticion?.abort(); peticion = null; secuencia++; }
    async function consultar() {
        if (!mostrar.checked || document.hidden || cerrada || peticion) return;
        const turno = ++secuencia;
        peticion = new AbortController();
        try {
            const r = await fetch(raiz.dataset.estado, {headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: peticion.signal});
            const cuerpo = await r.json();
            if (turno !== secuencia) return;
            if (!r.ok) {
                if ([401, 403].includes(r.status)) mostrar.checked = false;
                throw new Error([401, 403].includes(r.status) ? 'Vuelve a ingresar para consultar la simulación.' : 'No se pudo cargar la simulación.');
            }
            if (cuerpo.origen !== 'simulado' || !Array.isArray(cuerpo.puntos)) throw new Error('No se pudo leer la simulación.');
            estado = cuerpo;
            texto.textContent = nombres[cuerpo.estado] ?? 'Simulado';
            if (cuerpo.pendientes) texto.textContent += ' · Actualizando recorrido';
            raiz.dispatchEvent(new CustomEvent('simulacion-actualizada', {bubbles: true, detail: cuerpo}));
            dibujar();
        } catch (e) {
            if (turno !== secuencia || e.name === 'AbortError') return;
            texto.textContent = e instanceof TypeError || e instanceof SyntaxError ? 'No se pudo conectar. Inténtalo de nuevo.' : e.message;
            if (estado) texto.textContent += ' Se conserva el último estado.';
            if (!mostrar.checked) capa.clearLayers();
        } finally {
            if (turno === secuencia) {
                peticion = null;
                if (mostrar.checked && !cerrada && !document.hidden) temporizador = setTimeout(consultar, 3000);
            }
        }
    }
    mostrar.addEventListener('change', () => {
        cancelar();
        if (mostrar.checked) { texto.textContent = 'Cargando…'; consultar(); }
        else { capa.clearLayers(); elegido = null; resumen.textContent = ''; texto.textContent = 'Activa la capa para consultar la simulación.'; }
    });
    variable.addEventListener('change', dibujar);
    document.addEventListener('monitoreo-consulta-iniciada', () => { capa.clearLayers(); resumen.textContent = 'Cargando la selección del mapa…'; });
    document.addEventListener('monitoreo-actualizado', dibujar);
    document.addEventListener('unidades-cambiadas', dibujar);
    document.addEventListener('visibilitychange', () => { cancelar(); if (!document.hidden) consultar(); });
    window.addEventListener('pagehide', () => { cerrada = true; cancelar(); });
    window.addEventListener('pageshow', () => { if (cerrada) { cerrada = false; consultar(); } });
}
