import {clasesAgua, crearConsultaAgua, mensajeErrorAgua} from './agua-contrato.js';
import {cargarAgua, cargarCatalogoAgua} from './agua-api.js';
import {recordarTerritorio, territorioAnterior} from '../panel/contexto-ambiental.js';

function redondearAnillo(anillo) {
    const vertices=anillo.slice(0,-1);
    const contorno=vertices.flatMap((punto,i)=>{
        const anterior=vertices.at(i-1),siguiente=vertices[(i+1)%vertices.length];
        const distancia=vecino=>Math.hypot(vecino[0]-punto[0],vecino[1]-punto[1]);
        const recorte=Math.min(distancia(anterior)*.43,distancia(siguiente)*.43,.00011);
        if (!recorte) return [punto];
        const acercar=vecino=>punto.map((valor,eje)=>valor+(vecino[eje]-valor)*recorte/distancia(vecino));
        const entrada=acercar(anterior), salida=acercar(siguiente);
        // Tres muestras de una curva cuadrática entre los dos lados.
        return [0,.25,.5,.75,1].map(t=>entrada.map((valor,eje)=>
            (1-t)**2*valor+2*(1-t)*t*punto[eje]+t*t*salida[eje]));
    });
    return [...contorno,contorno[0]];
}

export function iniciarAgua(mapa,L) {
    const panel=document.querySelector('[data-panel-agua]');
    if (!panel) return;
    const prueba=panel.dataset.modo==='prueba';
    const el=id=>document.getElementById(id);
    const escribir=(id,texto)=>{el(id).textContent=texto;};
    const capas=L.layerGroup().addTo(mapa), capasPorId=new Map();
    const contornoOriginal=el('agua-contorno-original');
    const aLatLngs=anillos=>L.GeoJSON.coordsToLatLngs(anillos,1);
    contornoOriginal.addEventListener('change',()=>{
        for (const {capa,original,suavizado} of capasPorId.values()) {
            capa.setLatLngs(contornoOriginal.checked ? original : suavizado);
        }
    });
    const num=new Intl.NumberFormat('es-BO',{maximumFractionDigits:2});
    const formatoFecha=new Intl.DateTimeFormat('es-BO',{dateStyle:'medium',timeZone:'UTC'});
    const fecha=f=>formatoFecha.format(new Date(f+'T12:00:00Z'));
    const estilos={
        agua_persistente:{color:'#124e83',fillColor:'#1875bb',fillOpacity:.65},
        agua_nueva:{color:'#006a74',fillColor:'#36b6c9',fillOpacity:.58},
        agua_retirada:{color:'#8a570e',fillColor:'#eab45e',fillOpacity:.65},
        no_comparable:{color:'#555',fillColor:'#bbb',dashArray:'2 5',fillOpacity:.2},
    };
    let alMostrarResultado=()=>{},alMostrarError=()=>{};
    const indicadores={agua_persistente:'aguaPersistenteKm2',agua_nueva:'aguaNuevaKm2',agua_retirada:'aguaRetiradaKm2'};
    function encuadrar(bounds,maxZoom) {
        const grande=window.innerWidth>700;
        mapa.fitBounds(bounds,{
            paddingTopLeft:grande?[panel.offsetWidth+30,30]:[25,25],
            paddingBottomRight:grande?[70,70]:[25,panel.offsetHeight+25],
            maxZoom,animate:false,
        });
    }
    function seleccionar(feature) {
        for (const [id,{capa}] of capasPorId) {
            capa.setStyle({weight:id===feature.id?2.25:1,opacity:id===feature.id?1:.55});
        }
        const {capa}=capasPorId.get(feature.id);
        encuadrar(capa.getBounds(),15);
        const texto=document.createElement('span');
        texto.textContent=clasesAgua[feature.properties.clase];
        capa.bindPopup(texto).openPopup();
        for (const boton of el('agua-ubicaciones').querySelectorAll('button')) {
            const activo=boton.dataset.id===feature.id;
            boton.setAttribute('aria-pressed',String(activo));
            if (activo) boton.closest('details').open=true;
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
        for (const [clase,clave] of Object.entries(indicadores)) {
            const tarjeta=document.createElement('div');
            tarjeta.dataset.clase=clase;
            const termino=document.createElement('dt'),valor=document.createElement('dd');
            termino.textContent=clasesAgua[clase];
            valor.textContent=dato.resumen[clave]===null?'Sin datos':num.format(dato.resumen[clave])+' km²';
            tarjeta.append(termino,valor);
            el('agua-resumen').append(tarjeta);
        }
        escribir('agua-fuente',dato.fuente.nombre+'; '+dato.fuente.metodo+'; '+dato.fuente.resolucionMetros+' m.');
        for (const advertencia of dato.advertencias ?? []) {
            const nota=document.createElement('li');nota.textContent=advertencia;el('agua-advertencias').append(nota);
        }
        const grupos=new Map(Object.keys(clasesAgua).map(clase=>[clase,[]]));
        for (const feature of dato.capa.features) {
            const clase=feature.properties.clase;
            const anillos=feature.geometry.coordinates;
            const original=aLatLngs(anillos),suavizado=aLatLngs(anillos.map(redondearAnillo));
            const capa=L.polygon(contornoOriginal.checked ? original : suavizado,{
                ...estilos[clase],weight:1,opacity:.55,smoothFactor:0,
            }).addTo(capas);
            capasPorId.set(feature.id,{capa,original,suavizado});
            capa.on('click',()=>seleccionar(feature));
            grupos.get(clase).push(feature);
        }
        for (const [clase,features] of grupos) {
            if (!features.length) continue;
            const grupo=document.createElement('details'),titulo=document.createElement('summary');
            const categoria=document.createElement('span'),total=document.createElement('span');
            const lista=document.createElement('ul');
            grupo.dataset.clase=clase;
            categoria.textContent=clasesAgua[clase];
            total.className='agua-total';
            total.textContent=num.format(features.length);
            titulo.append(categoria,total);
            for (const [indice,feature] of features.entries()) {
                const fila=document.createElement('li'),boton=document.createElement('button');
                boton.type='button';
                boton.className='btn btn-outline-primary';
                boton.dataset.id=feature.id;
                boton.textContent='Ubicación '+(indice+1);
                boton.setAttribute('aria-label',clasesAgua[clase]+', ubicación '+(indice+1)+' (ver en el mapa)');
                boton.setAttribute('aria-pressed','false');
                boton.addEventListener('click',()=>seleccionar(feature));
                fila.append(boton);
                lista.append(fila);
            }
            grupo.append(titulo,lista);
            el('agua-ubicaciones').append(grupo);
        }
        el('agua-listado').hidden=!capasPorId.size;
        el('agua-contornos').hidden=!capasPorId.size;
        el('agua-resultado').hidden=false;alMostrarResultado(dato);
        if (capasPorId.size) {
            const bounds=L.latLngBounds([]);
            for (const {capa} of capasPorId.values()) bounds.extend(capa.getBounds());
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
        const territorios=JSON.parse(el('agua-territorios').textContent)
            .sort((a,b)=>a.nombre.localeCompare(b.nombre,'es-BO'));
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
        alMostrarResultado=dato=>{
            const efectivo=territorios.find(t=>t.identificador===dato.territorio.identificador);
            if (efectivo) recordarTerritorio({
                provincia:efectivo.tipo==='municipio' ? efectivo.padre : (efectivo.tipo==='provincia' ? efectivo.identificador : null),
                municipio:efectivo.tipo==='municipio' ? efectivo.identificador : null,
            });
            filtros(true);panel.scrollTop=0;
        };
        alMostrarError=()=>filtros(false);
        const productoElegido=()=>productos.find(p=>p.id===comparacion.value);
        function elegir() {
            const producto=productoElegido();
            boton.disabled=!producto;
            const url=new URL(location.href);
            url.search='';
            if (producto) {
                url.searchParams.set('producto',producto.id);
                history.replaceState(null,'',url);
                consultar(producto);
                return;
            }
            if (lugar.value) url.searchParams.set('territorio',lugar.value);
            history.replaceState(null,'',url);
            mostrar({estado:'error',mensaje:lugar.value
                ? 'Todavía no hay una comparación publicada para '+lugar.selectedOptions[0].textContent+'.'
                : 'Elige un lugar para ver sus comparaciones.'});
        }
        function llenarLugares() {
            const publicados=new Set(productos.map(p=>p.territorio.identificador));
            const disponibles=document.createElement('optgroup'),pendientes=document.createElement('optgroup');
            disponibles.label='Con comparaciones publicadas';
            pendientes.label='Sin comparaciones publicadas';
            for (const territorio of territorios) {
                const grupo=publicados.has(territorio.identificador) ? disponibles : pendientes;
                grupo.append(new Option(territorio.nombre,territorio.identificador));
            }
            lugar.replaceChildren(new Option('Elige un lugar de Santa Cruz',''));
            for (const grupo of [disponibles,pendientes]) {
                if (grupo.children.length) lugar.append(grupo);
            }
            lugar.disabled=false;
            escribir('agua-disponibilidad',publicados.size===1
                ? 'Por ahora, solo '+productos[0].territorio.nombre+' tiene fechas publicadas.'
                : 'Hay comparaciones publicadas para '+num.format(publicados.size)+' territorios.');
        }
        function llenarComparaciones(id=null) {
            const opciones=productos.filter(p=>p.territorio.identificador===lugar.value);
            const sinFecha=new Option(lugar.value && !opciones.length ? 'Sin fechas publicadas' : 'Elige una comparación','');
            sinFecha.disabled=opciones.length>0;
            comparacion.replaceChildren(sinFecha);
            for (const p of opciones) comparacion.add(new Option(fecha(p.comparacion.referencia)+' al '+fecha(p.comparacion.comparacion),p.id));
            comparacion.disabled=!opciones.length;
            comparacion.value=id ?? opciones[0]?.id ?? '';
            boton.disabled=!productoElegido();
        }
        async function actualizar(inicial=false) {
            const turno=++turnoCatalogo;
            const anterior=productoElegido(),seleccionAnterior=lugar.value;
            peticion?.abort();peticion=new AbortController();consultar.cancelar();
            lugar.disabled=true;comparacion.disabled=true;boton.disabled=true;
            filtros(false);mostrar({estado:'cargando'});
            try {
                const catalogo=await cargarCatalogoAgua(peticion.signal);
                if (turno!==turnoCatalogo) return;
                productos=[...catalogo.productos].sort((a,b)=>b.comparacion.comparacion.localeCompare(a.comparacion.comparacion));
                llenarLugares();
                const parametros=new URL(location.href).searchParams;
                const solicitado=inicial ? parametros.get('producto') : anterior?.id;
                const recordado=territorioAnterior(territorios);
                const territorioSolicitado=inicial
                    ? parametros.get('territorio') ?? recordado.municipio ?? recordado.provincia
                    : seleccionAnterior;
                if (inicial && !solicitado && parametros.has('territorio') && !territorios.some(t=>t.identificador===territorioSolicitado)) {
                    llenarComparaciones();
                    escribir('agua-estado','Ese lugar no está en el catálogo de Santa Cruz. Elige otro.');
                    return;
                }
                if (!solicitado && territorios.some(t=>t.identificador===territorioSolicitado)) {
                    lugar.value=territorioSolicitado;
                    llenarComparaciones();
                    elegir();
                    return;
                }
                if (!productos.length) {
                    llenarComparaciones();
                    escribir('agua-estado','No hay comparaciones publicadas para Santa Cruz.');
                    return;
                }
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
        comparacion.addEventListener('change',()=>{consultar.cancelar();if (comparacion.value) elegir();});
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
