const selector=document.getElementById('rep-serie');
if(selector){
    const filas=JSON.parse(document.getElementById('datos-reporte').textContent);
    const grupos=new Map();
    for(const f of filas){const clave=JSON.stringify([f.puntoCodigo,f.metrica,f.origen,f.fuente]);if(!grupos.has(clave))grupos.set(clave,[]);grupos.get(clave).push(f);}
    for(const [k,g] of grupos){const f=g[0];selector.add(new Option(`${f.punto} · ${f.variable} · ${f.origenNombre} · ${f.fuenteNombre}`,k));}
    const svg=document.getElementById('rep-grafico');
    function dibujar(){
        const g=grupos.get(selector.value).slice().sort((a,b)=>Date.parse(a.instante)-Date.parse(b.instante));
        const min=Math.min(...g.map(f=>f.valor)),max=Math.max(...g.map(f=>f.valor));
        const desde=Date.parse(g[0].instante),hasta=Date.parse(g[g.length-1].instante);
        svg.replaceChildren();
        const linea=document.createElementNS('http://www.w3.org/2000/svg','polyline');
        const puntos=g.map(f=>[30+(Date.parse(f.instante)-desde)/Math.max(1,hasta-desde)*740,225-(f.valor-min)/Math.max(1,max-min)*195]);
        linea.setAttribute('points',puntos.map(p=>p.join(',')).join(' '));linea.setAttribute('fill','none');linea.setAttribute('stroke','#168042');linea.setAttribute('stroke-width','3');svg.append(linea);
        for(const p of puntos){const c=document.createElementNS('http://www.w3.org/2000/svg','circle');c.setAttribute('cx',p[0]);c.setAttribute('cy',p[1]);c.setAttribute('r','4');c.setAttribute('fill','#168042');svg.append(c);}
        document.getElementById('rep-escala').textContent=`Mínimo ${min} · Máximo ${max} ${g[0].unidad} · ${g.length} ${g.length === 1 ? 'lectura' : 'lecturas'}.`;
        document.getElementById('rep-periodo').textContent=`${g[0].fecha} — ${g[g.length-1].fecha} · Hora de Bolivia.`;
    }
    selector.addEventListener('change',dibujar);dibujar();
}
