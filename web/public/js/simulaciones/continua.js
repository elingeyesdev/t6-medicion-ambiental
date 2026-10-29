// Consultas breves; ninguna conexión mantiene ocupado el servidor local.
export function conectarContinua(raiz) {
    let sesion = null, ocupado = false, temporizador = null, retirado = false;
    const texto = raiz.querySelector('[data-estado-texto]');
    const preparar = raiz.querySelector('[data-preparar]');
    const inyectar = raiz.querySelector('[data-inyectar]');
    const estados = {sin_preparar:'Todavía no hay un recorrido preparado.',lista:'Lista para iniciar',activa:'En marcha',pausada:'En pausa',detenida:'Detenida'};
    function mostrar(s) {
        sesion = s;
        texto.textContent = `${estados[s.estado] ?? s.estado}${s.instante ? ' · '+new Date(s.instante).toLocaleString('es-BO',{timeZone:'America/La_Paz'})+' · Bolivia' : ''}${s.paso !== undefined ? ' · Paso '+s.paso : ''}${s.pendientes ? ' · Recuperando recorrido' : ''}`;
        if (preparar) preparar.hidden = !(s.estado === 'sin_preparar' || s.estado === 'detenida' || (s.propia && s.estado === 'lista'));
        for (const b of raiz.querySelectorAll('[data-control]')) {
            b.disabled = !s.propia || (b.dataset.control === 'iniciar' && !['lista','pausada','detenida'].includes(s.estado)) || (b.dataset.control === 'pausar' && s.estado !== 'activa');
        }
        if (inyectar) {
            inyectar.hidden = !s.propia || !['activa','pausada'].includes(s.estado);
            const select = inyectar.elements.punto;
            const elegido = select.value;
            select.replaceChildren(...s.puntos.map(p=>new Option(p.nombre+' · '+p.pais,p.codigo)));
            if ([...select.options].some(o=>o.value===elegido)) select.value=elegido;
        }
        const filas = s.puntos.map(p=>{
            const tr=document.createElement('tr');
            for (const valor of [p.nombre,...['temperatura','humedad','aqi','ica','ruido'].map(k=>p.valores[k])]) {
                const td=document.createElement('td');td.textContent=valor ?? 'Sin dato';tr.append(td);
            }
            return tr;
        });
        if (!filas.length) { const tr=document.createElement('tr'),td=document.createElement('td');td.colSpan=6;td.textContent='Prepara e inicia una simulación para ver sus valores.';tr.append(td);filas.push(tr); }
        raiz.querySelector('[data-puntos]').replaceChildren(...filas);
        raiz.dispatchEvent(new CustomEvent('simulacion-actualizada',{bubbles:true,detail:s}));
    }
    async function consultar(datos) {
        if (ocupado || retirado) return;
        ocupado=true;clearTimeout(temporizador);
        try {
            const respuesta=await fetch(datos ? raiz.dataset.accion : raiz.dataset.estado,{
                method:datos?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(datos?{'Content-Type':'application/json','X-CSRF-TOKEN':preparar.querySelector('[name="_token"]').value}:{})},
                ...(datos?{body:JSON.stringify({...datos,revision:sesion?.revision??0})}:{})
            });
            const cuerpo=await respuesta.json();
            if (respuesta.status===409) { texto.textContent=cuerpo.error?.mensaje ?? 'La simulación cambió. Actualizando…'; }
            else if (!respuesta.ok) throw new Error(cuerpo.error?.mensaje ?? 'No se pudo consultar la simulación. Inténtalo de nuevo.');
            else mostrar(cuerpo);
        } catch(e) { texto.textContent=e instanceof TypeError||e instanceof SyntaxError?'No se pudo consultar la simulación. Inténtalo de nuevo.':e.message; }
        finally { ocupado=false;if(!retirado) temporizador=setTimeout(()=>{if(!document.hidden)consultar();else temporizador=setTimeout(()=>consultar(),10000);},3000); }
    }
    preparar?.addEventListener('submit',e=>{e.preventDefault();consultar({accion:'preparar',semilla:preparar.elements.semilla.value,intervalo_ms:Number(preparar.elements.intervalo_ms.value)});});
    inyectar?.addEventListener('submit',e=>{e.preventDefault();consultar({accion:'inyectar',punto:inyectar.elements.punto.value,valores:{[inyectar.elements.variable.value]:Number(inyectar.elements.valor.value)}});});
    for (const b of raiz.querySelectorAll('[data-control]')) b.addEventListener('click',()=>consultar({accion:b.dataset.control}));
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)consultar();});
    window.addEventListener('pagehide',()=>{retirado=true;clearTimeout(temporizador);});
    window.addEventListener('pageshow',()=>{if(retirado){retirado=false;consultar();}});
    consultar();
}
for (const raiz of document.querySelectorAll('[data-simulacion-continua]')) conectarContinua(raiz);
