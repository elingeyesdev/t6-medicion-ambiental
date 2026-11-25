const clave = 'geosur:territorio-consultado:v1';

export function recordarTerritorio({ provincia, municipio }) {
    try {
        sessionStorage.setItem(clave, JSON.stringify({ provincia, municipio }));
    } catch { /* El almacenamiento es opcional. */ }
}

export function territorioAnterior(catalogo) {
    try {
        const guardado = JSON.parse(sessionStorage.getItem(clave));
        if (!guardado) return {};
        const provincia = catalogo.find(t => t.tipo === 'provincia' && t.identificador === guardado.provincia);
        const municipio = catalogo.find(t => t.tipo === 'municipio' && t.identificador === guardado.municipio
            && (!provincia || t.padre === provincia.identificador));
        return { provincia: municipio?.padre ?? provincia?.identificador, municipio: municipio?.identificador };
    } catch { return {}; }
}
