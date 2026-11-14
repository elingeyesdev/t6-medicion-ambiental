import { aLocal, filtrosDelFormulario, solicitarJson, validarInstanteUrl, ErrorApi } from './contrato.js';
import { ConsultaFocosWeb } from './consulta.js';
import { VistaFocos } from './vista.js';
import { ajustarCalendarios, periodoReciente } from './periodo.js';
import { crearRelojServidor } from './reloj.js';

function iniciar() {
    const raiz = document.getElementById('visor-focos');
    if (!raiz) return;
    const reloj = crearRelojServidor(raiz.dataset.horaServidor);
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
    let intentoHora = 0;
    async function conHoraActual(accion) {
        const intento = ++intentoHora;
        const control = new AbortController();
        const limite = setTimeout(() => control.abort(), 5000);
        try {
            const url = new URL('../salud', raiz.dataset.api.replace(/\/$/, '') + '/');
            const respuesta = await solicitarJson(url.href, control.signal);
            if (intento !== intentoHora) return;
            reloj.sincronizar(respuesta.fecha);
            accion();
        } catch {
            if (intento === intentoHora) {
                consulta.editar();
                consulta.emitir({ fase: 'error', error: new ErrorApi('No se pudo comprobar la hora. Reintenta la consulta.', 'HORA_NO_DISPONIBLE'), pendiente: null });
            }
        } finally { clearTimeout(limite); }
    }
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
        ajustarCalendarios(desde, hasta, reloj.ahora());
    };
    const defecto = periodoReciente(7, reloj.ahora());
    restaurar(defecto);
    let inicial = { ...defecto };
    const parametros = new URLSearchParams(location.search);
    const consultaEnUrl = parametros.has('desde') && parametros.has('hasta');

    async function cargarCatalogo() {
        document.getElementById('estado-catalogo').textContent = 'Cargando…';
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
            // EnviroSense abre directamente el mapa, sin inventar detecciones iniciales.
            // La geometría de contexto es local; NASA solo se consulta al aplicar filtros.
            if (!consultaEnUrl && !consulta.estado.actual) {
                try {
                    const limite = await solicitarJson(`${raiz.dataset.api}/territorios/BO07/limite`, control.signal);
                    if (limite.territorio?.identificador !== 'BO07' || limite.limite?.type !== 'Feature') throw new Error('Límite incompatible');
                    // Una consulta iniciada mientras llega el límite no pierde sus puntos.
                    if (!consulta.estado.actual) vista.mapa.mostrar([], limite.limite, null);
                } catch {
                    if (!consulta.estado.actual) vista.mapa.mostrar([], null, 'No se pudo cargar el contorno inicial de Santa Cruz. Puedes consultar los focos igualmente.');
                }
            }
            document.getElementById('campos-filtros').disabled = false;
            document.getElementById('estado-catalogo').textContent = '';
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
                await consulta.consultar(filtrosDelFormulario(formulario, reloj.ahora()));
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
    formulario.addEventListener('input', () => { ++intentoHora; consulta.editar(); });
    formulario.addEventListener('change', () => { ++intentoHora; consulta.editar(); });
    for (const fecha of [desde, hasta]) {
        fecha.addEventListener('focus', () => ajustarCalendarios(desde, hasta, reloj.ahora()));
        fecha.addEventListener('input', () => ajustarCalendarios(desde, hasta, reloj.ahora()));
        fecha.addEventListener('change', () => ajustarCalendarios(desde, hasta, reloj.ahora()));
    }
    for (const boton of document.querySelectorAll('[data-periodo-dias]')) {
        boton.addEventListener('click', () => conHoraActual(() => {
            restaurar({ provincia: provincia.value, municipio: municipio.value, confianza: document.getElementById('confianza').value, ...periodoReciente(Number(boton.dataset.periodoDias), reloj.ahora()) });
            consulta.editar();
        }));
    }
    document.getElementById('limpiar-periodo').addEventListener('click', () => {
        ++intentoHora;
        desde.value = ''; hasta.value = ''; ajustarCalendarios(desde, hasta, reloj.ahora()); consulta.editar(); desde.focus();
    });
    formulario.addEventListener('submit', e => {
        e.preventDefault();
        conHoraActual(() => {
            ajustarCalendarios(desde, hasta, reloj.ahora());
            try { consulta.consultar(filtrosDelFormulario(formulario, reloj.ahora())); }
            catch (error) { consulta.editar(); consulta.emitir({ fase: 'error', error, pendiente: null }); }
        });
    });
    document.getElementById('cancelar-filtros').addEventListener('click', () => { ++intentoHora; restaurar(consulta.estado.actual?.filtros ?? inicial); consulta.restaurar(); });
    document.getElementById('anterior').addEventListener('click', () => consulta.paginar(consulta.estado.actual.respuesta.paginacion.pagina - 1));
    document.getElementById('siguiente').addEventListener('click', () => consulta.paginar(consulta.estado.actual.respuesta.paginacion.siguiente));
    document.getElementById('reintentar').addEventListener('click', () => {
        if (consulta.estado.pendiente) consulta.reintentar();
        else formulario.requestSubmit();
    });
    document.getElementById('reiniciar').addEventListener('click', () => { ++intentoHora; consulta.reiniciar(); });
    document.getElementById('reintentar-catalogo').addEventListener('click', cargarCatalogo);
    cargarCatalogo();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
else iniciar();
