const selector=document.getElementById('rep-serie');
if(selector){
    const filas=JSON.parse(document.getElementById('datos-reporte').textContent);
    const grupos=new Map();
    for(const f of filas){const clave=JSON.stringify([f.puntoCodigo,f.metrica,f.origen,f.fuente]);if(!grupos.has(clave))grupos.set(clave,[]);grupos.get(clave).push(f);}
    for(const [k,g] of grupos){const f=g[0];selector.add(new Option(`${f.punto} · ${f.variable} · ${f.origenNombre} · ${f.fuenteNombre}`,k));}
    const svg=document.getElementById('rep-grafico');
    function dibujar(){
        const g=grupos.get(selector.value).slice().sort((a,b)=>Date.parse(a.instante)-Date.parse(b.instante));
        const barras=document.getElementById('rep-tipo').value==='barras';
        const validos=g.filter(f=>f.valor!==null);
        svg.replaceChildren();
        if(!validos.length){document.getElementById('rep-escala').textContent='No hay valores disponibles para esta serie.';document.getElementById('rep-periodo').textContent='';return;}
        const min=Math.min(...validos.map(f=>f.valor),...(barras?[0]:[])),max=Math.max(...validos.map(f=>f.valor),...(barras?[0]:[]));
        const desde=Date.parse(g[0].instante),hasta=Date.parse(g[g.length-1].instante);
        svg.replaceChildren();
        const linea=document.createElementNS('http://www.w3.org/2000/svg','path');
        const puntos=g.map(f=>f.valor===null?null:[30+(Date.parse(f.instante)-desde)/Math.max(1,hasta-desde)*740,225-(f.valor-min)/Math.max(1,max-min)*195]);
        if(!barras){let mover=true;const camino=puntos.map(p=>{if(!p){mover=true;return '';}const comando=mover?'M':'L';mover=false;return comando+p.join(',');}).join(' ');linea.setAttribute('d',camino);linea.setAttribute('fill','none');linea.setAttribute('stroke','#168042');linea.setAttribute('stroke-width','3');svg.append(linea);}
        const cero=225-(0-min)/Math.max(1,max-min)*195;
        for(const p of puntos){if(!p)continue;const c=document.createElementNS('http://www.w3.org/2000/svg',barras?'rect':'circle');if(barras){const ancho=Math.min(30,700/g.length);c.setAttribute('x',p[0]-ancho/2);c.setAttribute('y',Math.min(cero,p[1]));c.setAttribute('width',ancho);c.setAttribute('height',Math.max(1,Math.abs(cero-p[1])));}else{c.setAttribute('cx',p[0]);c.setAttribute('cy',p[1]);c.setAttribute('r','4');}c.setAttribute('fill','#168042');svg.append(c);}
        document.getElementById('rep-escala').textContent=`Mínimo ${min} · Máximo ${max} ${g[0].unidad} · ${validos.length} ${validos.length === 1 ? 'lectura' : 'lecturas'} · ${g.length-validos.length} sin dato.`;
        document.getElementById('rep-periodo').textContent=`${g[0].fecha} — ${g[g.length-1].fecha} · Hora de Bolivia.`;
    }
    selector.addEventListener('change',dibujar);dibujar();
    document.getElementById('rep-tipo').addEventListener('change',dibujar);
}
