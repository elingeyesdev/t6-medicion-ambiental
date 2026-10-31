const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const vm = require('node:vm');

function pantalla(filas) {
    class Elemento {
        constructor() { this.children=[];this.attributes={};this.events={};this.value='';this.textContent=''; }
        replaceChildren(...n) { this.children=n; }
        append(n) { this.children.push(n); }
        add(n) { this.children.push(n);if(this.children.length===1)this.value=n.value; }
        setAttribute(k,v) { this.attributes[k]=String(v); }
        addEventListener(k,f) { this.events[k]=f; }
    }
    const ids=Object.fromEntries(['rep-serie','rep-tipo','rep-grafico','rep-escala','rep-periodo','datos-reporte'].map(k=>[k,new Elemento()]));
    ids['datos-reporte'].textContent=JSON.stringify(filas);ids['rep-tipo'].value='linea';
    const document={getElementById:k=>ids[k],createElementNS:(_,tipo)=>Object.assign(new Elemento(),{tipo})};
    vm.runInNewContext(readFileSync(join(__dirname,'../../public/js/reportes/grafico.js'),'utf8'),{document,Option:class {constructor(text,value){this.text=text;this.value=value;}}});
    return ids;
}
const fila=(valor,hora,extra={})=>({valor,instante:`2026-10-29T${hora}:00:00Z`,fecha:hora+':00',punto:'Santa Cruz',puntoCodigo:'santacruz',metrica:'lluvia',variable:'Lluvia',unidad:'mm',origen:'estimado',origenNombre:'Estimado',fuente:'modelo',fuenteNombre:'Modelo',...extra});

test('un faltante interrumpe la curva y cero sigue siendo una lectura',()=>{
    const p=pantalla([fila(0,'08'),fila(null,'09'),fila(10,'10')]);
    const camino=p['rep-grafico'].children.find(n=>n.tipo==='path').attributes.d;
    assert.equal((camino.match(/M/g)||[]).length,2);
    assert.equal(camino.includes('L'),false);
    assert.equal(p['rep-grafico'].children.filter(n=>n.tipo==='circle').length,2);
    assert.match(p['rep-escala'].textContent,/2 lecturas · 1 sin dato/);
    p['rep-tipo'].value='barras';p['rep-tipo'].events.change();
    assert.equal(p['rep-grafico'].children.filter(n=>n.tipo==='rect').length,2);
    assert.equal(p['rep-grafico'].children.some(n=>JSON.stringify(n.attributes).includes('NaN')),false);
});

test('una serie completamente ausente tiene un mensaje y ninguna cifra inventada',()=>{
    const p=pantalla([fila(null,'08'),fila(null,'09')]);
    assert.equal(p['rep-grafico'].children.length,0);
    assert.match(p['rep-escala'].textContent,/No hay valores disponibles/);
});

test('dos procedencias de una misma variable mantienen series separadas',()=>{
    const p=pantalla([fila(0,'08'),fila(50,'08',{origen:'simulado',fuente:'sim-global'})]);
    assert.equal(p['rep-serie'].children.length,2);
    assert.match(p['rep-escala'].textContent,/1 lectura/);
});
