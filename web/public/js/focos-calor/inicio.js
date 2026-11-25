import { recordarTerritorio, territorioAnterior } from '../panel/contexto-ambiental.js';
import { aLocal, filtrosDelFormulario, solicitarJson, validarInstanteUrl } from './contrato.js';
import { ConsultaFocosWeb } from './consulta.js';
import { VistaFocos } from './vista.js';
import { ajustarCalendarios, periodoReciente, ultimoPeriodoDisponible } from './periodo.js';
import { crearRelojServidor } from './reloj.js';
import { iniciarCalendarios } from './calendario.js';

function iniciar() {
    const raiz = document.getElementById('visor-focos');
    if (!raiz) return;
    // El servidor entrega la hora al cargar la vista. El reloj avanza con tiempo monotónico.
    const reloj = crearRelojServidor(raiz.dataset.horaServidor);
    const formulario = document.getElementById('filtros-focos');
    const provincia = document.getElementById('provincia');
    const municipio = document.getElementById('municipio');
    const desde = document.getElementById('desde');
    const hasta = document.getElementById('hasta');
    iniciarCalendarios(desde, hasta);
    const vista = new VistaFocos();
    const consulta = new ConsultaFocosWeb(raiz.dataset.api, estado => {
        vista.mostrar(estado, reloj.ahora());
        if (estado.fase === 'listo' && estado.actual) {
            recordarTerritorio(estado.actual.respuesta.consulta.filtrosEfectivos ?? estado.actual.filtros);
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
        ajustarCalendarios(desde, hasta, reloj.ahora());
    };
    const defecto = periodoReciente(7, reloj.ahora());
    restaurar(defecto);
    let inicial = { ...defecto };
    const parametros = new URLSearchParams(location.search);
    const periodoEnUrl = parametros.has('desde') || parametros.has('hasta');
    const consultaEnUrl = periodoEnUrl || ['provincia', 'municipio', 'confianza'].some(k => parametros.has(k));

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
            if (!consultaEnUrl && !consulta.estado.borrador && !consulta.estado.actual) {
                restaurar({ ...defecto, ...territorioAnterior(territorios) });
            }
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
        } catch (error) {
            document.getElementById('estado-catalogo').textContent = `No se pudo preparar la selección: ${error.message || 'comprueba la conexión'}`;
            document.getElementById('reintentar-catalogo').hidden = false;
            return;
        } finally { clearTimeout(tiempo); }
        if (!consultaEnUrl) {
            if (!consulta.estado.actual && !consulta.estado.borrador && consulta.estado.fase === 'inicial') {
                await consulta.consultar(filtrosDelFormulario(formulario, reloj.ahora()));
            }
            return;
        }
        try {
            const preseleccion = Object.fromEntries(['desde', 'hasta', 'provincia', 'municipio', 'confianza'].filter(k => parametros.has(k)).map(k => [k, parametros.get(k)]));
            // Una URL desconocida no se transforma silenciosamente en el departamento completo.
            for (const [nombre, tipo] of [['provincia', 'provincia'], ['municipio', 'municipio']]) {
                if (preseleccion[nombre] && !territorios.some(t => t.identificador === preseleccion[nombre] && t.tipo === tipo)) throw new Error('La URL contiene un territorio desconocido. Revisa los filtros antes de consultar.');
            }
            if (preseleccion.provincia && preseleccion.municipio && territorios.find(t => t.identificador === preseleccion.municipio)?.padre !== preseleccion.provincia) throw new Error('La provincia y el municipio de la URL son incompatibles.');
            if (preseleccion.municipio && !preseleccion.provincia) preseleccion.provincia = territorios.find(t => t.identificador === preseleccion.municipio).padre;
            if (preseleccion.confianza && !['alta', 'nominal', 'baja', 'desconocida'].includes(preseleccion.confianza)) throw new Error('La confianza de la URL no es válida.');
            if (periodoEnUrl) {
                validarInstanteUrl(preseleccion.desde);
                validarInstanteUrl(preseleccion.hasta);
            }
            restaurar({ ...defecto, ...preseleccion });
            const filtros = filtrosDelFormulario(formulario, reloj.ahora());
            inicial = { ...filtros };
            await consulta.consultar(filtros);
        } catch (error) {
            consulta.editar();
            consulta.emitir({ fase: 'error', error, pendiente: null });
        }
    }
    provincia.addEventListener('change', () => actualizarMunicipios());
    municipio.addEventListener('change', () => {
        if (municipio.value && !provincia.value) { const valor = municipio.value; provincia.value = territorios.find(t => t.identificador === valor).padre; actualizarMunicipios(valor); }
    });
    formulario.addEventListener('input', () => { consulta.editar(); });
    formulario.addEventListener('change', () => { consulta.editar(); });
    for (const fecha of [desde, hasta]) {
        fecha.addEventListener('focus', () => ajustarCalendarios(desde, hasta, reloj.ahora()));
        fecha.addEventListener('input', () => ajustarCalendarios(desde, hasta, reloj.ahora()));
        fecha.addEventListener('change', () => ajustarCalendarios(desde, hasta, reloj.ahora()));
    }
    for (const boton of document.querySelectorAll('[data-periodo-dias]')) {
        boton.addEventListener('click', () => {
            restaurar({ provincia: provincia.value, municipio: municipio.value, confianza: document.getElementById('confianza').value, ...periodoReciente(Number(boton.dataset.periodoDias), reloj.ahora()) });
            consulta.editar();
        });
    }
    document.getElementById('limpiar-periodo').addEventListener('click', () => {
        desde.value = ''; hasta.value = ''; ajustarCalendarios(desde, hasta, reloj.ahora()); consulta.editar(); desde.focus();
    });
    formulario.addEventListener('submit', e => {
        e.preventDefault();
        ajustarCalendarios(desde, hasta, reloj.ahora());
        try { consulta.consultar(filtrosDelFormulario(formulario, reloj.ahora())); }
        catch (error) { consulta.editar(); consulta.emitir({ fase: 'error', error, pendiente: null }); }
    });
    document.getElementById('ultimo-periodo-disponible').addEventListener('click', () => {
        const actual = consulta.estado.actual;
        const periodo = ultimoPeriodoDisponible(actual?.respuesta.cobertura?.periodoDisponible, reloj.ahora());
        if (!periodo) return;
        restaurar({ ...actual.filtros, ...periodo });
        formulario.requestSubmit();
    });
    document.getElementById('cancelar-filtros').addEventListener('click', () => { restaurar(consulta.estado.actual?.filtros ?? inicial); consulta.restaurar(); });
    document.getElementById('anterior').addEventListener('click', () => consulta.paginar(consulta.estado.actual.respuesta.paginacion.pagina - 1));
    document.getElementById('siguiente').addEventListener('click', () => consulta.paginar(consulta.estado.actual.respuesta.paginacion.siguiente));
    document.getElementById('reintentar').addEventListener('click', () => {
        if (consulta.estado.pendiente) consulta.reintentar();
        else formulario.requestSubmit();
    });
    document.getElementById('reiniciar').addEventListener('click', () => { consulta.reiniciar(); });
    document.getElementById('reintentar-catalogo').addEventListener('click', cargarCatalogo);
    cargarCatalogo();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
else iniciar();
