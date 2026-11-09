import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {cargarAgua, crearConsultaAguaApi} from '../../public/js/mapas/agua-api.js';
import {crearConsultaAgua} from '../../public/js/mapas/agua-contrato.js';

const filtros = {territorio:'BO070101', referencia:'2024-09-01', comparacion:'2025-03-07'};
const fixture = async () => JSON.parse(await readFile(new URL('../../../compartido/api/inundaciones/ejemplos/cambio.json', import.meta.url), 'utf8'));
// Solo una respuesta sintética de contrato para probar el transporte, no evidencia ambiental.
async function respuestaContrato() {
    const dato = await fixture();
    dato.tipoEvidencia = 'resultado_validado';
    return dato;
}
const json = (dato, status=200) => new Response(JSON.stringify(dato), {
    status, headers:{'Content-Type':'application/json; charset=utf-8'},
});

test('la consulta normal rechaza ejemplos y la revisión local los permite expresamente', async () => {
    const dato=await fixture(), normal=[], local=[], ambiguo=[];
    await crearConsultaAgua(async()=>dato, s=>normal.push(s))('caso');
    await crearConsultaAgua(async()=>dato, s=>local.push(s), {permitirPrueba:true})('caso');
    await crearConsultaAgua(async()=>dato, s=>ambiguo.push(s), {permitirPrueba:'true'})('caso');
    assert.equal(normal.at(-1).estado,'error');
    assert.equal(local.at(-1).estado,'resultado');
    assert.equal(ambiguo.at(-1).estado,'error');
});

test('el transporte usa la ruta fija, señal, JSON y credenciales del mismo sitio', async () => {
    const signal=new AbortController().signal, dato=await respuestaContrato();
    const resultado=await cargarAgua(filtros,signal,async(url, opciones)=>{
        assert.equal(url,'/mapa/agua/consulta?territorio=BO070101&referencia=2024-09-01&comparacion=2025-03-07');
        assert.equal(opciones.signal,signal);
        assert.equal(opciones.headers.Accept,'application/json');
        assert.equal(opciones.credentials,'same-origin');
        assert.equal(opciones.mode,'same-origin');
        assert.equal(opciones.redirect,'error');
        assert.equal(opciones.cache,'no-store');
        return json(dato);
    });
    assert.deepEqual(resultado,dato);
});

test('rechaza filtros incompletos, booleanos o extra antes de hacer la petición', async () => {
    for(const consulta of [null,{}, {...filtros, modo:'prueba'}, {...filtros,territorio:true}, {...filtros,referencia:''}]) {
        await assert.rejects(cargarAgua(consulta,null,()=>assert.fail('No debe consultar')), {codigo:'FILTROS_INVALIDOS'});
    }
});

test('un HTTP 200 con datos de prueba no se publica como resultado de la API', async () => {
    await assert.rejects(cargarAgua(filtros,null,async()=>json(await fixture())));
});

test('rechaza HTML, JSON roto y redirecciones sin convertirlos en datos vacíos', async () => {
    const respuestas=[
        ()=>new Response('<html>Ingresar</html>',{headers:{'Content-Type':'text/html'}}),
        ()=>new Response('{', {headers:{'Content-Type':'application/json'}}),
        ()=>({status:200,ok:true,redirected:true,headers:new Headers({'Content-Type':'application/json'})}),
    ];
    for(const respuesta of respuestas) await assert.rejects(cargarAgua(filtros,null,async()=>respuesta()), {codigo:'RESPUESTA_INVALIDA'});
});

test('sesión, permisos y límite de consultas tienen mensajes breves sin exponer el error del servidor', async () => {
    for(const [http,texto] of [[401,'Vuelve a ingresar para consultar.'],[403,'Tu cuenta no tiene acceso a esta consulta.'],[429,'Espera un momento y vuelve a intentarlo.']]) {
        const estados=[];
        await crearConsultaAguaApi(s=>estados.push(s),{peticion:async()=>new Response('Detalle interno',{status:http})})(filtros);
        assert.equal(estados.at(-1).estado,'error');
        assert.equal(estados.at(-1).mensaje,texto);
    }
});

test('producto no disponible borra el resultado anterior y no se reemplaza por una figura de prueba', async () => {
    const estados=[], dato=await respuestaContrato();let fallo=false;
    const consultar=crearConsultaAguaApi(s=>estados.push(s),{peticion:async()=>fallo?
        json({error:{codigo:'PRODUCTO_NO_DISPONIBLE',mensaje:'Detalle interno'}},503):json(dato)});
    await consultar(filtros);fallo=true;await consultar(filtros);
    assert.equal(estados[1].estado,'resultado');
    assert.equal(estados.at(-2).estado,'cargando');
    assert.deepEqual(estados.at(-1),{estado:'error',mensaje:'No hay una comparación disponible para ese lugar y fechas.'});
});

test('errores desconocidos y fallos de red no filtran textos técnicos', async () => {
    for(const peticion of [
        async()=>json({error:{codigo:'INTERNO_PRIVADO',mensaje:'<script>interno</script>'}},500),
        async()=>{throw new TypeError('SQL servidor privado');},
    ]) {
        const estados=[];
        await crearConsultaAguaApi(s=>estados.push(s),{peticion})(filtros);
        assert.deepEqual(estados.at(-1),{estado:'error',mensaje:'No se pudo cargar la comparación. Inténtalo otra vez.'});
    }
});

test('rechaza una respuesta exitosa incompatible con el contrato', async () => {
    const dato=await respuestaContrato();dato.resumen.aguaNuevaKm2=-1;
    await assert.rejects(cargarAgua(filtros,null,async()=>json(dato)));
});

test('la respuesta atrasada no reemplaza la consulta nueva y se cancela su señal', async () => {
    const estados=[], pendientes=[], dato=await respuestaContrato();
    const consultar=crearConsultaAguaApi(s=>estados.push(s), {peticion:(url,opciones)=>new Promise(resolve=>pendientes.push({resolve,signal:opciones.signal}))});
    const primera=consultar(filtros), segunda=consultar({...filtros,territorio:'BO0701'});
    assert.equal(pendientes[0].signal.aborted,true);
    pendientes[1].resolve(json({error:{codigo:'TERRITORIO_NO_DISPONIBLE'}},404));await segunda;
    pendientes[0].resolve(json(dato));await primera;
    assert.deepEqual(estados.at(-1),{estado:'error',mensaje:'No hay datos disponibles para ese lugar.'});
    assert.equal(estados.filter(s=>s.estado==='resultado').length,0);
});

test('el catálogo exige productos identificados y no confunde vacío con error', async()=>{
    const {leerCatalogoAgua}=await import('../../public/js/mapas/agua-api.js');
    const d=await respuestaContrato();
    const producto={id:'a'.repeat(64),territorio:d.territorio,comparacion:d.comparacion,estado:d.estado};
    const base={versionContrato:'agua-0.1-candidata',estado:'disponible',productos:[producto]};
    assert.deepEqual(leerCatalogoAgua(base),base);
    assert.deepEqual(leerCatalogoAgua({...base,estado:'sin_productos_validados',productos:[]}).productos,[]);
    for (const cambiar of [
        x=>x.productos={},
        x=>x.estado='sin_productos_validados',
        x=>x.productos.push(x.productos[0]),
        x=>x.productos.push({...x.productos[0],id:'b'.repeat(64)}),
        x=>x.productos[0].id='../privado',
        x=>x.productos[0].comparacion.referencia='2025-03-07',
        x=>x.productos[0].territorio.alcance='todo_el_municipio',
    ]) {
        const copia=structuredClone(base);cambiar(copia);
        assert.throws(()=>leerCatalogoAgua(copia));
    }
});
test('el catálogo se consulta con la sesión web en el mismo sitio',async()=>{
    const {cargarCatalogoAgua}=await import('../../public/js/mapas/agua-api.js');
    const d=await cargarCatalogoAgua(null,async(url,opciones)=>{
        assert.equal(url,'/mapa/agua/catalogo');
        assert.equal(opciones.credentials,'same-origin');
        return json({versionContrato:'agua-0.1-candidata',estado:'sin_productos_validados',productos:[]});
    });
    assert.deepEqual(d.productos,[]);
});
test('un resultado de otro lugar o fechas nunca reemplaza la comparación elegida',async()=>{
    for (const modo of ['lugar','fecha']) {
        const d=await respuestaContrato();
        if (modo==='lugar') d.territorio.identificador='BO07';
        else d.comparacion.comparacion='2025-03-08';
        await assert.rejects(cargarAgua(filtros,null,async()=>json(d)),{codigo:'RESPUESTA_INVALIDA'});
    }
});
test('si cambió la versión publicada se exige actualizar la lista',async()=>{
    const d=await respuestaContrato();
    const respuesta=()=>new Response(JSON.stringify(d),{headers:{'Content-Type':'application/json','X-Producto-SHA256':'a'.repeat(64)}});
    assert.deepEqual(await cargarAgua(filtros,null,async()=>respuesta(),'a'.repeat(64)),d);
    await assert.rejects(cargarAgua(filtros,null,async()=>respuesta(),'b'.repeat(64)),{codigo:'CATALOGO_CAMBIO'});
});
test('cancelar una consulta impide que reaparezca al actualizar el catálogo',async()=>{
    const estados=[],dato=await respuestaContrato();let resolver;
    const consultar=crearConsultaAgua(()=>new Promise(r=>{resolver=r;}),s=>estados.push(s));
    const pendiente=consultar(filtros);consultar.cancelar();resolver(dato);await pendiente;
    assert.deepEqual(estados,[{estado:'cargando'}]);
});
