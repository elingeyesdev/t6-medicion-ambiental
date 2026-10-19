import { aLocal, filtrosDelFormulario, solicitarJson, validarInstanteUrl } from './contrato.js';
import { ConsultaFocosWeb } from './consulta.js';
import { VistaFocos } from './vista.js';
import { ajustarCalendarios } from './periodo.js';

function iniciar() {
    const raiz = document.getElementById('visor-focos');
    if (!raiz) return;
    const formulario = document.getElementById('filtros-focos');
    const provincia = document.getElementById('provincia');
    const municipio = document.getElementById('municipio');
    const desde = document.getElementById('desde');
    const hasta = document.getElementById('hasta');
    const vista = new VistaFocos();
    const consulta = new ConsultaFocosWeb(raiz.dataset.api, estado => {
        vista.mostrar(estado);
        if (estado.fase === 'listo' && estado.actual) {
            const url = new URL(location.href);
            url.search = new URLSearchParams(estado.actual.filtros).toString();
            // Compartir filtros; nunca prometer un snapshot o página vigente al volver a abrir.
            history.replaceState(null, '', url);
        }
    });
    let territorios = [];
    const opciones = (select, elementos, titulo, valor = '') => {
        select.replaceChildren(new Option(titulo, ''), ...elementos.map(t => new Option(t.nombre, t.identificador)));
        select.value = elementos.some(t => t.identificador === valor) ? valor : '';
    };
    const actualizarMunicipios = (valor = municipio.value) => opciones(municipio, territorios.filter(t => t.tipo === 'municipio' && (!provincia.value || t.padre === provincia.value)), 'Todos los municipios', valor);
    const restaurar = filtros => {
        provincia.value = filtros?.provincia ?? '';
        actualizarMunicipios(filtros?.municipio ?? '');
        document.getElementById('confianza').value = filtros?.confianza ?? '';
        if (filtros?.desde) document.getElementById('desde').value = aLocal(filtros.desde);
        if (filtros?.hasta) document.getElementById('hasta').value = aLocal(filtros.hasta);
        ajustarCalendarios(desde, hasta);
    };
    const hoy = aLocal(new Date().toISOString()).slice(0, 10);
    const fin = new Date(`${hoy}T00:00:00-04:00`);
    const defecto = { desde: new Date(fin.getTime() - 86400000).toISOString(), hasta: fin.toISOString() };
    restaurar(defecto);
    let inicial = { ...defecto };
    const parametros = new URLSearchParams(location.search);
    const consultaEnUrl = parametros.has('desde') && parametros.has('hasta');

    async function cargarCatalogo() {
        document.getElementById('estado-catalogo').textContent = 'Cargando catálogo territorial…';
        document.getElementById('reintentar-catalogo').hidden = true;
        const control = new AbortController();
        const tiempo = setTimeout(() => control.abort(), 15000);
        try {
            const r = await solicitarJson(`${raiz.dataset.api}/territorios?tamanioPagina=500`, control.signal);
            if (!Array.isArray(r.datos) || r.paginacion?.siguiente !== null || r.datos.length !== r.paginacion.total) throw new Error('Catálogo incompleto');
            territorios = r.datos;
            vista.nombres = new Map(territorios.map(t => [t.identificador, t.nombre]));
            opciones(provincia, territorios.filter(t => t.tipo === 'provincia'), 'Todo Santa Cruz');
            actualizarMunicipios('');
            document.getElementById('campos-filtros').disabled = false;
            document.getElementById('estado-catalogo').textContent = 'Límites de referencia OCHA/HDX · catálogo en revisión. Las autonomías conservan su denominación.';
            if (consultaEnUrl) {
                const preseleccion = Object.fromEntries(['desde', 'hasta', 'provincia', 'municipio', 'confianza'].filter(k => parametros.has(k)).map(k => [k, parametros.get(k)]));
                // Una URL desconocida no se transforma silenciosamente en el departamento completo.
                for (const [nombre, tipo] of [['provincia', 'provincia'], ['municipio', 'municipio']]) {
                    if (preseleccion[nombre] && !territorios.some(t => t.identificador === preseleccion[nombre] && t.tipo === tipo)) throw new Error('La URL contiene un territorio desconocido. Revisa los filtros antes de consultar.');
                }
                if (preseleccion.provincia && preseleccion.municipio && territorios.find(t => t.identificador === preseleccion.municipio)?.padre !== preseleccion.provincia) throw new Error('La provincia y el municipio de la URL son incompatibles.');
                if (preseleccion.municipio && !preseleccion.provincia) preseleccion.provincia = territorios.find(t => t.identificador === preseleccion.municipio).padre;
                if (preseleccion.confianza && !['alta', 'nominal', 'baja', 'desconocida'].includes(preseleccion.confianza)) throw new Error('La confianza de la URL no es válida.');
                validarInstanteUrl(preseleccion.desde); validarInstanteUrl(preseleccion.hasta);
                restaurar(preseleccion);
                inicial = { ...preseleccion };
                await consulta.consultar(filtrosDelFormulario(formulario));
            }
        } catch (error) {
            document.getElementById('estado-catalogo').textContent = `No se pudo preparar la selección: ${error.message || 'comprueba la conexión'}`;
            document.getElementById('reintentar-catalogo').hidden = false;
        } finally { clearTimeout(tiempo); }
    }
    provincia.addEventListener('change', () => actualizarMunicipios());
    municipio.addEventListener('change', () => {
        if (municipio.value && !provincia.value) { const valor = municipio.value; provincia.value = territorios.find(t => t.identificador === valor).padre; actualizarMunicipios(valor); }
    });
    formulario.addEventListener('input', () => consulta.editar());
    formulario.addEventListener('change', () => consulta.editar());
    for (const fecha of [desde, hasta]) {
        fecha.addEventListener('input', () => ajustarCalendarios(desde, hasta));
        fecha.addEventListener('change', () => ajustarCalendarios(desde, hasta));
    }
    document.getElementById('limpiar-periodo').addEventListener('click', () => {
        desde.value = ''; hasta.value = ''; ajustarCalendarios(desde, hasta); consulta.editar(); desde.focus();
    });
    formulario.addEventListener('submit', e => {
        e.preventDefault();
        try { consulta.consultar(filtrosDelFormulario(formulario)); }
        catch (error) { consulta.editar(); consulta.emitir({ fase: 'error', error, pendiente: null }); }
    });
    document.getElementById('cancelar-filtros').addEventListener('click', () => { restaurar(consulta.estado.actual?.filtros ?? inicial); consulta.restaurar(); });
    document.getElementById('anterior').addEventListener('click', () => consulta.paginar(consulta.estado.actual.respuesta.paginacion.pagina - 1));
    document.getElementById('siguiente').addEventListener('click', () => consulta.paginar(consulta.estado.actual.respuesta.paginacion.siguiente));
    document.getElementById('reintentar').addEventListener('click', () => consulta.reintentar());
    document.getElementById('reiniciar').addEventListener('click', () => consulta.reiniciar());
    document.getElementById('reintentar-catalogo').addEventListener('click', cargarCatalogo);
    cargarCatalogo();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
else iniciar();
