import { iniciarMapaBase } from '../mapas/base.js';

const catalogo=JSON.parse(document.getElementById('catalogo-escenarios').textContent);
const metrica=document.getElementById('sim-metrica');
function escenarios(inicial=false) {
    for(const select of document.querySelectorAll('.sim-escenario')) {
        const elegido=inicial?select.dataset.elegido:'';
        select.replaceChildren(...catalogo[metrica.value].map(e=>new Option(e.nombre,e.id)),new Option('Personalizado','personalizado'));
        if([...select.options].some(o=>o.value===elegido)) select.value=elegido;
    }
}
escenarios(true);metrica.addEventListener('change',()=>escenarios());

const datos=JSON.parse(document.getElementById('reproduccion-simulacion').textContent);
if(datos) iniciar(datos);

function iniciar(config) {
    let sesion=config.sesion;
    let posicion=sesion.posicion;
    let reloj=null;
    let ocupado=false;
    const estado=document.getElementById('sim-estado');
    const barra=document.getElementById('sim-tiempo');
    const L=window.L;
    const mapa=L.map('sim-mapa',{scrollWheelZoom:true,zoomAnimation:false}).setView([-20,-60],3);
    iniciarMapaBase(mapa,m=>{estado.textContent=m;});
    new ResizeObserver(()=>mapa.invalidateSize({animate:false})).observe(document.getElementById('sim-mapa'));
    const colores=['#156b42','#147ab4','#c45f18','#8057a1','#b33c52','#4b697a','#84751a','#0b8b85','#78451d','#347dc1'];
    const marcadores=[];
    const fecha=new Intl.DateTimeFormat('es-BO',{timeZone:'America/La_Paz',dateStyle:'short',timeStyle:'short'});
    const valores=document.getElementById('sim-valores');
    for(const [i,serie] of sesion.resultado.series.entries()) {
        const titulo=document.createElement('span');titulo.textContent=serie.nombre;
        const marcador=L.circleMarker([serie.latitud,serie.longitud],{radius:10,weight:3,color:'#fff',fillColor:colores[i],fillOpacity:1}).bindTooltip(titulo).addTo(mapa);
        marcadores.push(marcador);
        const p=document.createElement('p');p.style.borderColor=colores[i];p.append(titulo.cloneNode(true),document.createElement('strong'));valores.append(p);
    }
    for(const limite of config.limites) L.geoJSON(limite,{style:{color:'#d88a1c',weight:2,fillOpacity:0.06}}).addTo(mapa);
    mapa.fitBounds(sesion.resultado.series.map(s=>[s.latitud,s.longitud]),{padding:[45,45],maxZoom:7,animate:false});
    const svg=document.getElementById('sim-grafico');
    const todos=sesion.resultado.series.flatMap(s=>s.valores);
    const minimo=Math.min(...todos),maximo=Math.max(...todos);
    for(const [i,serie] of sesion.resultado.series.entries()) {
        const linea=document.createElementNS('http://www.w3.org/2000/svg','polyline');
        linea.setAttribute('points',serie.valores.map((v,j)=>`${20+j/Math.max(1,serie.valores.length-1)*600},${195-(v-minimo)/Math.max(1,maximo-minimo)*170}`).join(' '));
        linea.setAttribute('fill','none');linea.setAttribute('stroke',colores[i]);linea.setAttribute('stroke-width','3');svg.append(linea);
    }
    document.getElementById('sim-rango').textContent=`Valores simulados entre ${minimo} y ${maximo} ${sesion.resultado.unidad}.`;
    const cursor=document.createElementNS('http://www.w3.org/2000/svg','line');cursor.setAttribute('y1','10');cursor.setAttribute('y2','210');cursor.setAttribute('stroke','#344556');cursor.setAttribute('stroke-dasharray','4');svg.append(cursor);
    function mostrar() {
        barra.value=posicion;
        document.getElementById('sim-fecha').textContent=fecha.format(new Date(sesion.resultado.instantes[posicion]))+' · Bolivia';
        for(const [i,serie] of sesion.resultado.series.entries()) {
            valores.children[i].querySelector('strong').textContent=serie.valores[posicion]+' '+sesion.resultado.unidad;
            marcadores[i].setRadius(8+(serie.valores[posicion]-minimo)/Math.max(1,maximo-minimo)*12);
        }
        cursor.setAttribute('x1',20+posicion/Math.max(1,sesion.resultado.instantes.length-1)*600);cursor.setAttribute('x2',cursor.getAttribute('x1'));
    }
    function parar(){clearInterval(reloj);reloj=null;}
    async function accion(tipo) {
        if(ocupado) return;
        ocupado=true;parar();
        try {
            const r=await fetch(config.accion,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('input[name="_token"]').value},body:JSON.stringify({accion:tipo,revision:sesion.revision,posicion}),credentials:'same-origin'});
            const cuerpo=await r.json();
            if(!r.ok) throw new Error(cuerpo.error?.mensaje??'No se pudo guardar el cambio. Vuelve a abrir la simulación.');
            sesion=cuerpo;posicion=sesion.posicion;estado.textContent=sesion.estado;mostrar();
            if(tipo==='iniciar') reloj=setInterval(()=>{if(posicion<sesion.resultado.instantes.length-1){posicion++;mostrar();}else{parar();accion('finalizar');}},700);
        } catch(e){estado.textContent=e instanceof TypeError||e instanceof SyntaxError?'No se pudo conectar. Vuelve a abrir la simulación.':e.message;}
        finally{ocupado=false;}
    }
    for(const boton of document.querySelectorAll('[data-accion]')) boton.addEventListener('click',()=>accion(boton.dataset.accion));
    barra.addEventListener('input',()=>{parar();posicion=Number(barra.value);mostrar();});
    barra.addEventListener('change',()=>{if(sesion.estado==='activa') accion('pausar');});
    window.addEventListener('pagehide',parar);
    if(sesion.estado==='activa') estado.textContent='Lista para continuar';
    mostrar();
}
