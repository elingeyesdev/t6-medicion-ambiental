import {clasesAgua, crearConsultaAgua} from './agua-contrato.js';
export function iniciarRevisionAgua(mapa,L) {
    const panel=document.getElementById('revision-agua');
    if(!panel) return;
    const ejemplos=JSON.parse(document.getElementById('agua-ejemplos').textContent);
    const capas=L.layerGroup().addTo(mapa), capasPorId=new Map();
    const estilos={agua_persistente:{color:'#124e83',fillColor:'#1875bb'},agua_nueva:{color:'#006a74',fillColor:'#36b6c9',dashArray:'6 3'},agua_retirada:{color:'#8a570e',fillColor:'#eab45e'},no_comparable:{color:'#555',fillColor:'#bbb',dashArray:'2 5',fillOpacity:.2}};
    const el=id=>document.getElementById(id), escribir=(id,t)=>{el(id).textContent=t;};
    const num=new Intl.NumberFormat('es-BO',{maximumFractionDigits:2});
    const etiquetas={aguaPersistenteKm2:'Agua en ambas fechas',aguaNuevaKm2:'Agua nueva',aguaRetiradaKm2:'Agua retirada'};
    function seleccionar(f) {
        for(const [id,c] of capasPorId)c.setStyle({weight:id===f.id?5:2});
        const c=capasPorId.get(f.id);mapa.fitBounds(c.getBounds(),{paddingTopLeft:window.innerWidth>700?[panel.offsetWidth+30,30]:[25,25],paddingBottomRight:window.innerWidth>700?[70,70]:[25,panel.offsetHeight+25],maxZoom:14,animate:false});
        const texto=document.createElement('span');texto.textContent=clasesAgua[f.properties.clase];c.bindPopup(texto).openPopup();
        for(const b of el('agua-ubicaciones').querySelectorAll('button'))b.setAttribute('aria-pressed',String(b.dataset.id===f.id));
    }
    function mostrar(s) {
        capas.clearLayers();capasPorId.clear();el('agua-ubicaciones').replaceChildren();el('agua-resumen').replaceChildren();el('agua-resultado').hidden=true;
        if(s.estado==='cargando'){escribir('agua-estado','Cargando…');return;}
        if(s.estado==='error'){escribir('agua-estado',s.mensaje);return;}
        const d=s.dato;
        escribir('agua-estado',d.estado==='sin_datos'?'No hay datos comparables en esta zona.':d.estado==='sin_cambios'?'No se observaron cambios en el área comparable.':'Comparación de agua');
        escribir('agua-lugar',d.territorio.nombre+' · recorte parcial');
        escribir('agua-fechas',d.comparacion.referencia+' → '+d.comparacion.comparacion);
        escribir('agua-cobertura','Área comparable: '+num.format(d.cobertura.areaComparableKm2)+' de '+num.format(d.cobertura.areaEstudiadaKm2)+' km² ('+num.format(100*d.cobertura.fraccionComparable)+' %).');
        for(const [k,n] of Object.entries(etiquetas)){const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=n;dd.textContent=d.resumen[k]===null?'Sin datos':num.format(d.resumen[k])+' km²';el('agua-resumen').append(dt,dd);}
        escribir('agua-fuente',d.fuente.nombre+' · '+d.fuente.metodo+' · '+d.fuente.resolucionMetros+' m.');
        for(const f of d.capa.features){
            const c=L.geoJSON(f,{style:{...estilos[f.properties.clase],weight:2,fillOpacity: f.properties.clase==='no_comparable'?.2:.6}}).addTo(capas);
            capasPorId.set(f.id,c);c.on('click',()=>seleccionar(f));
            const li=document.createElement('li'),b=document.createElement('button');b.type='button';b.className='btn btn-outline-primary';b.dataset.id=f.id;b.textContent=clasesAgua[f.properties.clase]+' · Ver en el mapa';b.setAttribute('aria-pressed','false');b.addEventListener('click',()=>seleccionar(f));li.append(b);el('agua-ubicaciones').append(li);
        }
        el('agua-resultado').hidden=false;
        if(capasPorId.size){const bounds=L.latLngBounds([]);for(const c of capasPorId.values())bounds.extend(c.getBounds());mapa.fitBounds(bounds,{paddingTopLeft:window.innerWidth>700?[panel.offsetWidth+30,30]:[25,25],paddingBottomRight:window.innerWidth>700?[70,70]:[25,panel.offsetHeight+25],maxZoom:13,animate:false});}
    }
    const consultar=crearConsultaAgua(async caso=>{if(caso==='error')throw new Error('Prueba controlada');return ejemplos[caso];},mostrar,{permitirPrueba:true});
    el('agua-consultar').addEventListener('click',()=>consultar(el('agua-caso').value));
    el('agua-cerrar').addEventListener('click',()=>{panel.hidden=true;el('agua-reabrir').hidden=false;el('agua-reabrir').focus();});
    el('agua-reabrir').addEventListener('click',()=>{panel.hidden=false;el('agua-reabrir').hidden=true;el('agua-cerrar').focus();});
    L.DomEvent.disableClickPropagation(panel);L.DomEvent.disableScrollPropagation(panel);
    mapa.zoomControl?.setPosition('bottomright');consultar('cambio');
}
