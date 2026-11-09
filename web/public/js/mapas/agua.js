import {clasesAgua, crearConsultaAgua, mensajeErrorAgua} from './agua-contrato.js';
import {cargarAgua, cargarCatalogoAgua} from './agua-api.js';

export function iniciarAgua(mapa,L) {
    const panel=document.querySelector('[data-panel-agua]');
    if (!panel) return;
    const prueba=panel.dataset.modo==='prueba';
    const el=id=>document.getElementById(id);
    const escribir=(id,texto)=>{el(id).textContent=texto;};
    const capas=L.layerGroup().addTo(mapa), capasPorId=new Map();
    const num=new Intl.NumberFormat('es-BO',{maximumFractionDigits:2});
    const formatoFecha=new Intl.DateTimeFormat('es-BO',{dateStyle:'medium',timeZone:'UTC'});
    const fecha=f=>formatoFecha.format(new Date(f+'T12:00:00Z'));
    const estilos={
        agua_persistente:{color:'#124e83',fillColor:'#1875bb'},
        agua_nueva:{color:'#006a74',fillColor:'#36b6c9',dashArray:'6 3'},
        agua_retirada:{color:'#8a570e',fillColor:'#eab45e'},
        no_comparable:{color:'#555',fillColor:'#bbb',dashArray:'2 5',fillOpacity:.2},
    };
    let alMostrarResultado=()=>{},alMostrarError=()=>{};
    const etiquetas={aguaPersistenteKm2:'Agua en ambas fechas',aguaNuevaKm2:'Agua nueva',aguaRetiradaKm2:'Agua retirada'};
    function encuadrar(bounds,maxZoom) {
        const grande=window.innerWidth>700;
        mapa.fitBounds(bounds,{
            paddingTopLeft:grande?[panel.offsetWidth+30,30]:[25,25],
            paddingBottomRight:grande?[70,70]:[25,panel.offsetHeight+25],
            maxZoom,animate:false,
        });
    }
    function seleccionar(feature) {
        for (const [id,capa] of capasPorId) capa.setStyle({weight:id===feature.id?5:2});
        const capa=capasPorId.get(feature.id);
        encuadrar(capa.getBounds(),14);
        const texto=document.createElement('span');
        texto.textContent=clasesAgua[feature.properties.clase];
        capa.bindPopup(texto).openPopup();
        for (const boton of el('agua-ubicaciones').querySelectorAll('button')) {
            boton.setAttribute('aria-pressed',String(boton.dataset.id===feature.id));
        }
    }
    function mostrar(estado) {
        capas.clearLayers();capasPorId.clear();
        el('agua-ubicaciones').replaceChildren();el('agua-resumen').replaceChildren();el('agua-advertencias').replaceChildren();
        el('agua-resultado').hidden=true;
        if (estado.estado==='cargando') {escribir('agua-estado','Cargando…');return;}
        if (estado.estado==='error') {alMostrarError();escribir('agua-estado',estado.mensaje);return;}
        const dato=estado.dato;
        escribir('agua-estado',dato.estado==='sin_datos'?'No hay datos comparables en esta zona.':
            dato.estado==='sin_cambios'?'No se observaron cambios en el área comparable.':'Comparación cargada.');
        escribir('agua-lugar',dato.territorio.nombre+' (recorte parcial)');
        escribir('agua-fechas',fecha(dato.comparacion.referencia)+' al '+fecha(dato.comparacion.comparacion));
        escribir('agua-cobertura','Área comparable: '+num.format(dato.cobertura.areaComparableKm2)+' de '+
            num.format(dato.cobertura.areaEstudiadaKm2)+' km² ('+num.format(100*dato.cobertura.fraccionComparable)+' %).');
        for (const [clave,nombre] of Object.entries(etiquetas)) {
            const termino=document.createElement('dt'),valor=document.createElement('dd');
            termino.textContent=nombre;
            valor.textContent=dato.resumen[clave]===null?'Sin datos':num.format(dato.resumen[clave])+' km²';
            el('agua-resumen').append(termino,valor);
        }
        escribir('agua-fuente',dato.fuente.nombre+'; '+dato.fuente.metodo+'; '+dato.fuente.resolucionMetros+' m.');
        for (const advertencia of dato.advertencias ?? []) {
            const nota=document.createElement('li');nota.textContent=advertencia;el('agua-advertencias').append(nota);
        }
        for (const feature of dato.capa.features) {
            const capa=L.geoJSON(feature,{style:{...estilos[feature.properties.clase],weight:2,
                fillOpacity:feature.properties.clase==='no_comparable'?.2:.6}}).addTo(capas);
            capasPorId.set(feature.id,capa);
            capa.on('click',()=>seleccionar(feature));
            const fila=document.createElement('li'),boton=document.createElement('button');
            boton.type='button';boton.className='btn btn-outline-primary';
            boton.dataset.id=feature.id;boton.textContent=clasesAgua[feature.properties.clase]+' (ver en el mapa)';
            boton.setAttribute('aria-pressed','false');
            boton.addEventListener('click',()=>seleccionar(feature));
            fila.append(boton);el('agua-ubicaciones').append(fila);
        }
        el('agua-resultado').hidden=false;alMostrarResultado();
        if (capasPorId.size) {
            const bounds=L.latLngBounds([]);
            for (const capa of capasPorId.values()) bounds.extend(capa.getBounds());
            encuadrar(bounds,13);
        }
    }

    let consultar, cancelarCatalogo=()=>{};
    if (prueba) {
        const ejemplos=JSON.parse(el('agua-ejemplos').textContent);
        consultar=crearConsultaAgua(async caso=>{
            if (caso==='error') throw new Error('Prueba controlada');
            return ejemplos[caso];
        },mostrar,{permitirPrueba:true});
        el('agua-consultar').addEventListener('click',()=>consultar(el('agua-caso').value));
        consultar('cambio');
    } else {
        let productos=[],peticion=null,turnoCatalogo=0;
        consultar=crearConsultaAgua((producto,signal)=>cargarAgua({
            territorio:producto.territorio.identificador,
            referencia:producto.comparacion.referencia,
            comparacion:producto.comparacion.comparacion,
        },signal,globalThis.fetch,producto.id),mostrar);
        const lugar=el('agua-territorio'),comparacion=el('agua-comparacion'),boton=el('agua-consultar');
        const formulario=el('agua-seleccion'),cambiar=el('agua-filtros'),movil=matchMedia('(max-width:700px)');
        function filtros(cerrar) {
            const ocultar=movil.matches && cerrar;
            if (ocultar && formulario.contains(document.activeElement)) cambiar.focus();
            formulario.hidden=ocultar;cambiar.setAttribute('aria-expanded',String(!ocultar));
            cambiar.textContent=ocultar?'Cambiar lugar o fechas':'Ocultar filtros';
            el('agua-fechas').hidden=!ocultar;
        }
        cambiar.addEventListener('click',()=>filtros(!formulario.hidden));
        const redimensionar=()=>filtros(false);movil.addEventListener('change',redimensionar);
        alMostrarResultado=()=>{filtros(true);panel.scrollTop=0;};alMostrarError=()=>filtros(false);
        const productoElegido=()=>productos.find(p=>p.id===comparacion.value);
        function elegir() {
            const producto=productoElegido();
            boton.disabled=!producto;
            if (!producto) { mostrar({estado:'error',mensaje:'Elige un lugar y una comparación.'}); return; }
            const url=new URL(location.href);url.search='';
            url.searchParams.set('producto',producto.id);
            history.replaceState(null,'',url);
            consultar(producto);
        }
        function llenarComparaciones(id=null) {
            const opciones=productos.filter(p=>p.territorio.identificador===lugar.value);
            comparacion.replaceChildren(new Option('Elige una comparación',''));
            for (const p of opciones) comparacion.add(new Option(fecha(p.comparacion.referencia)+' al '+fecha(p.comparacion.comparacion),p.id));
            comparacion.disabled=!opciones.length;
            comparacion.value=id ?? opciones[0]?.id ?? '';
            boton.disabled=!productoElegido();
        }
        async function actualizar(inicial=false) {
            const turno=++turnoCatalogo;
            const anterior=productoElegido();
            peticion?.abort();peticion=new AbortController();consultar.cancelar();
            lugar.disabled=true;comparacion.disabled=true;boton.disabled=true;
            filtros(false);mostrar({estado:'cargando'});
            try {
                const catalogo=await cargarCatalogoAgua(peticion.signal);
                if (turno!==turnoCatalogo) return;
                productos=[...catalogo.productos].sort((a,b)=>b.comparacion.comparacion.localeCompare(a.comparacion.comparacion));
                lugar.replaceChildren(new Option('Elige un lugar',''));
                const vistos=new Set();
                for (const p of productos) {
                    if (!vistos.has(p.territorio.identificador)) {
                        lugar.add(new Option(p.territorio.nombre,p.territorio.identificador));
                        vistos.add(p.territorio.identificador);
                    }
                }
                lugar.disabled=!productos.length;
                if (!productos.length) {
                    comparacion.replaceChildren();
                    escribir('agua-estado','No hay comparaciones disponibles.');
                    return;
                }
                const solicitado=inicial?new URL(location.href).searchParams.get('producto'):anterior?.id;
                let elegido=productos.find(p=>p.id===solicitado);
                if (inicial && solicitado && !elegido) {
                    lugar.value='';llenarComparaciones();
                    escribir('agua-estado','Esa comparación no está disponible. Elige otra.');
                    return;
                }
                elegido??=productos.find(p=>anterior && p.territorio.identificador===anterior.territorio.identificador &&
                    p.comparacion.referencia===anterior.comparacion.referencia &&
                    p.comparacion.comparacion===anterior.comparacion.comparacion) ?? productos[0];
                lugar.value=elegido.territorio.identificador;llenarComparaciones(elegido.id);elegir();
            } catch (error) {
                if (turno===turnoCatalogo && error?.name!=='AbortError') {
                    escribir('agua-estado',mensajeErrorAgua(error));
                }
            }
        }
        lugar.addEventListener('change',()=>{consultar.cancelar();llenarComparaciones();elegir();});
        comparacion.addEventListener('change',()=>{consultar.cancelar();elegir();});
        el('agua-seleccion').addEventListener('submit',evento=>{evento.preventDefault();elegir();});
        el('agua-actualizar').addEventListener('click',()=>actualizar());
        cancelarCatalogo=()=>{++turnoCatalogo;peticion?.abort();movil.removeEventListener('change',redimensionar);};
        actualizar(true);
    }
    el('agua-cerrar').addEventListener('click',()=>{panel.hidden=true;el('agua-reabrir').hidden=false;el('agua-reabrir').focus();});
    el('agua-reabrir').addEventListener('click',()=>{panel.hidden=false;el('agua-reabrir').hidden=true;el('agua-cerrar').focus();});
    L.DomEvent.disableClickPropagation(panel);L.DomEvent.disableScrollPropagation(panel);
    mapa.zoomControl?.setPosition('bottomright');
    mapa.on('unload',()=>{consultar.cancelar();cancelarCatalogo();});
}
