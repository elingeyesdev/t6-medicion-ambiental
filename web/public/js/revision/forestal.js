import { iniciarMapaBase } from '../mapas/base.js';

// 25 tonos estables para 2001–2025. El año permanece escrito en lista y selección.
export const colorAnio = anio => 'hsl(' + (((anio - 2001) * 137.508) % 360 + 360) % 360 + ' 65% 38%)';
export function formatearArea(valor) {
    if (valor === null || valor === undefined || valor === '' || !Number.isFinite(Number(valor))) return 'No informado';
    return new Intl.NumberFormat('es-BO', { maximumFractionDigits: 3 }).format(Number(valor));
}
const texto = valor => typeof valor === 'string' || typeof valor === 'number' ? String(valor) : 'No informado';

export function leerConsulta(id, desde, hasta) {
    if (typeof id !== 'string' || id.length !== 64 || !/^[a-f0-9]{64}$/.test(id)) throw new Error('Revisa el código del paquete.');
    if (typeof desde !== 'string' || typeof hasta !== 'string' || desde.length !== 4 || hasta.length !== 4 || !/^[0-9]{4}$/.test(desde) || !/^[0-9]{4}$/.test(hasta) || Number(desde) > Number(hasta)) {
        throw new Error('Indica años de cuatro cifras, con desde anterior o igual a hasta.');
    }
    return { id, desde, hasta };
}

export function validarRespuesta(datos, consulta) {
    const r = datos?.resumen;
    if (datos?.revision !== true || datos.id !== consulta.id || !r || typeof r !== 'object'
        || r.consulta?.desde !== Number(consulta.desde) || r.consulta?.hasta !== Number(consulta.hasta)
        || !Array.isArray(r.serie) || datos.capa?.type !== 'FeatureCollection' || !Array.isArray(datos.capa.features)) {
        throw new Error('La respuesta no corresponde al paquete y período consultados.');
    }
    const enPeriodo = anio => Number.isInteger(anio) && anio >= Number(consulta.desde) && anio <= Number(consulta.hasta);
    const vistos = new Set();
    for (const fila of r.serie) {
        if (!enPeriodo(fila?.anio) || vistos.has(fila.anio)) throw new Error('La serie recibida no corresponde al período consultado.');
        vistos.add(fila.anio);
    }
    for (const f of datos.capa.features) {
        if (f?.type !== 'Feature' || !enPeriodo(f.properties?.anio) || !['Polygon', 'MultiPolygon'].includes(f.geometry?.type)) {
            throw new Error('La capa recibida no corresponde al período consultado.');
        }
    }
    return datos;
}

/** Textos del paquete siempre por textContent; no se interpretan como HTML. */
export function pintarResumen(doc, datos) {
    const r = datos.resumen;
    const elemento = (tag, valor) => { const e = doc.createElement(tag); e.textContent = texto(valor); return e; };
    const nodo = id => doc.getElementById(id);
    nodo('rf-periodo').textContent = 'Período consultado: ' + r.consulta.desde + '–' + r.consulta.hasta;
    nodo('rf-alcance').textContent = 'Alcance: ventana técnica del paquete. Coordenadas oeste, sur, este, norte: '
        + (Array.isArray(r.bbox) ? r.bbox.map(texto).join(', ') : 'No informadas') + '.';
    nodo('rf-resumen').replaceChildren();
    for (const [titulo, valor] of [['Área cartografiada (ha)', formatearArea(r.total_periodo_ha)], ['Píxeles de pérdida del período', r.conteos_pixeles?.perdida_periodo]]) {
        const grupo = doc.createElement('div'); grupo.append(elemento('dt', titulo), elemento('dd', valor)); nodo('rf-resumen').append(grupo);
    }
    nodo('rf-serie').replaceChildren();
    for (const fila of r.serie) {
        const tr = doc.createElement('tr'), th = elemento('th', fila.anio); th.scope = 'row';
        tr.append(th, elemento('td', formatearArea(fila.perdida_ha))); nodo('rf-serie').append(tr);
    }
    if (!r.serie.length) { const tr = doc.createElement('tr'), td = elemento('td', 'Sin serie informada para este período.'); td.colSpan = 2; tr.append(td); nodo('rf-serie').append(tr); }
    nodo('rf-fuente').replaceChildren();
    nodo('rf-contexto').replaceChildren();
    const f = r.fuente ?? {}, c = r.criterio ?? {}, v = r.ventana_pixeles;
    const fuente = [['Producto', f.producto], ['Tesela', f.tesela], ['Atribución', f.atribucion],
        ['Licencia', f.licencia], ['Licencia · referencia', f.licencia_url], ['Documentación', f.documentacion], ['Transformaciones', f.transformaciones],
        ['Estado del recorte', r.estado],
        ['Fracción de observaciones', r.cobertura_observaciones_fraccion]];
    for (const [clave, valor] of [['Versión', f.version], ['Base de cobertura', c.base == null ? null : 'Año ' + texto(c.base) + ', umbral propuesto ' + texto(c.operador) + ' ' + texto(c.porcentaje) + ' %'],
        ['Ventana de píxeles', v ? 'Columna ' + texto(v.columna) + ', fila ' + texto(v.fila) + ', ancho ' + texto(v.ancho) + ', alto ' + texto(v.alto) : null]]) nodo('rf-contexto').append(elemento('dt', clave), elemento('dd', valor));
    for (const [clave, valor] of fuente) nodo('rf-fuente').append(elemento('dt', clave), elemento('dd', valor));
    nodo('rf-limites').replaceChildren();
    if (Array.isArray(r.limites) && r.limites.length) for (const limite of r.limites) nodo('rf-limites').append(elemento('li', limite));
    else nodo('rf-limites').append(elemento('li', 'Límites del paquete no informados.'));
    nodo('rf-leyenda').replaceChildren();
    const anios = [...new Set(datos.capa.features.map(fila => fila.properties.anio))].sort((a, b) => a - b);
    for (const anio of anios) {
        const li = doc.createElement('li'), color = doc.createElement('i'); color.style.setProperty('--rf-color', colorAnio(anio)); color.setAttribute('aria-hidden', 'true');
        li.append(color, elemento('span', anio)); nodo('rf-leyenda').append(li);
    }
    if (!anios.length) nodo('rf-leyenda').append(elemento('li', 'No hay polígonos de pérdida para este período.'));
}

export function iniciarRevisionForestal({ doc = document, L = window.L, fetcher = window.fetch.bind(window), base = iniciarMapaBase } = {}) {
    const raiz = doc.getElementById('revision-forestal');
    if (!raiz) return null;
    const nodo = id => doc.getElementById(id), form = nodo('rf-formulario'), resultados = nodo('rf-resultados');
    let mapa = null, capa = null, seleccion = null, abortador = null, turno = 0;
    const avisarMapa = mensaje => { nodo('rf-estado-mapa').hidden = false; nodo('rf-estado-mapa').textContent = mensaje; };
    const limpiar = () => {
        resultados.hidden = true;
        if (capa && mapa) mapa.removeLayer(capa);
        capa = null; seleccion = null;
        for (const id of ['rf-serie', 'rf-resumen', 'rf-fuente', 'rf-contexto', 'rf-limites', 'rf-leyenda', 'rf-poligonos']) nodo(id).replaceChildren();
        nodo('rf-centrar').disabled = true;
        nodo('rf-seleccion').textContent = 'Selecciona un polígono en el mapa o en la lista para centrarlo.';
    };
    const centrar = geometria => { const limites = geometria?.getBounds(); if (limites?.isValid()) mapa.fitBounds(limites, { padding: [24, 24], maxZoom: 18, animate: false }); };
    const crearMapa = datos => {
        nodo('rf-poligonos').replaceChildren();
        // La lista y el resumen siguen disponibles si Leaflet no carga.
        if (!mapa && L) {
            mapa = L.map(nodo('rf-mapa'), { scrollWheelZoom: true }).setView([-17.5, -63.5], 7);
            base(mapa, avisarMapa, 'rf-base');
        }
        if (!L || !mapa) avisarMapa('El mapa no está disponible. Consulta la serie y la lista de polígonos.');
        const botones = [];
        const elegir = (geometria, boton, f, indice) => {
            if (seleccion && capa) capa.resetStyle(seleccion);
            seleccion = geometria;
            if (geometria) { geometria.setStyle({ weight: 3, color: '#163e2b', fillOpacity: .85 }); geometria.bringToFront(); centrar(geometria); }
            for (const b of botones) b.setAttribute('aria-pressed', String(b === boton));
            nodo('rf-seleccion').textContent = 'Polígono ' + (indice + 1) + ', año ' + f.properties.anio + ', área (ha): ' + formatearArea(f.properties.area_ha);
        };
        const agregar = (f, geometria, indice) => {
            const li = doc.createElement('li'), boton = doc.createElement('button'); boton.type = 'button'; boton.className = 'btn btn-outline-primary';
            boton.textContent = 'Polígono ' + (indice + 1) + ', año ' + f.properties.anio + ', ' + formatearArea(f.properties.area_ha) + ' ha';
            boton.setAttribute('aria-pressed', 'false'); botones.push(boton);
            boton.addEventListener('click', () => elegir(geometria, boton, f, indice));
            if (geometria) geometria.on('click', () => elegir(geometria, boton, f, indice));
            li.append(boton); nodo('rf-poligonos').append(li);
        };
        if (mapa) {
            let indice = 0;
            capa = L.geoJSON(datos.capa, {
                style: f => ({ color: colorAnio(f.properties.anio), fillColor: colorAnio(f.properties.anio), weight: 1, fillOpacity: .65 }),
                onEachFeature: (f, geometria) => agregar(f, geometria, indice++),
            }).addTo(mapa);
            mapa.invalidateSize();
            centrar(capa);
            const bbox = datos.resumen.bbox;
            if (!datos.capa.features.length && Array.isArray(bbox) && bbox.length === 4 && bbox.every(Number.isFinite)) {
                mapa.fitBounds([[bbox[1], bbox[0]], [bbox[3], bbox[2]]], { padding: [24, 24], maxZoom: 18, animate: false });
            }
            nodo('rf-centrar').disabled = datos.capa.features.length === 0;
        } else datos.capa.features.forEach((f, indice) => agregar(f, null, indice));
        if (!datos.capa.features.length) {
            const li = doc.createElement('li'); li.textContent = 'No hay polígonos de pérdida en el período consultado.'; nodo('rf-poligonos').append(li);
        }
    };
    const consultar = async evento => {
        evento?.preventDefault();
        abortador?.abort(); const actual = ++turno; limpiar();
        let consulta;
        try { consulta = leerConsulta(nodo('rf-id').value, nodo('rf-desde').value, nodo('rf-hasta').value); }
        catch (e) { nodo('rf-estado').textContent = e.message; nodo('rf-consultar').disabled = false; resultados.setAttribute('aria-busy', 'false'); return; }
        abortador = new AbortController(); const control = abortador;
        const tiempo = setTimeout(() => control.abort(), 15000);
        resultados.setAttribute('aria-busy', 'true'); nodo('rf-consultar').disabled = true;
        nodo('rf-estado').textContent = 'Cargando…';
        try {
            const respuesta = await fetcher(raiz.dataset.consulta + '?' + new URLSearchParams(consulta), {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: control.signal,
            });
            if (actual !== turno) return;
            if (!respuesta.headers.get('content-type')?.includes('application/json')) throw new Error('No se pudo consultar el paquete. Revisa tu sesión y vuelve a intentarlo.');
            const datos = await respuesta.json();
            if (actual !== turno) return;
            if (!respuesta.ok) throw new Error(typeof datos?.error?.mensaje === 'string' ? datos.error.mensaje : 'No se pudo consultar el paquete. Revisa tu sesión y permisos.');
            validarRespuesta(datos, consulta);
            pintarResumen(doc, datos);
            resultados.hidden = false;
            crearMapa(datos);
            nodo('rf-estado').textContent = datos.capa.features.length ? 'Consulta completada para la ventana del paquete.' : 'Consulta completada. No hay polígonos de pérdida para este período en el paquete.';
        } catch (e) {
            if (actual !== turno) return;
            limpiar();
            nodo('rf-estado').textContent = e.name === 'AbortError' ? 'La consulta tardó demasiado. Vuelve a intentarlo.' : (e instanceof TypeError ? 'No se pudo conectar. Vuelve a intentarlo.' : e.message);
        } finally {
            clearTimeout(tiempo);
            if (actual === turno) { nodo('rf-consultar').disabled = false; resultados.setAttribute('aria-busy', 'false'); }
        }
    };
    form.addEventListener('submit', consultar);
    form.addEventListener('input', () => {
        turno++; abortador?.abort(); limpiar(); nodo('rf-consultar').disabled = false; resultados.setAttribute('aria-busy', 'false');
        nodo('rf-estado').textContent = 'Filtros modificados. Pulsa Consultar para aplicar los cambios.';
    });
    nodo('rf-centrar').addEventListener('click', () => centrar(capa));
    return { consultar };
}
