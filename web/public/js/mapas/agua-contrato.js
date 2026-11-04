export const clasesAgua = Object.freeze({agua_persistente:'Agua en ambas fechas',agua_nueva:'Agua nueva',agua_retirada:'Agua retirada',no_comparable:'Sin comparación'});
export function leerAgua(d, permitirPrueba = false) {
    if (d?.versionContrato !== 'agua-0.1-candidata' || !['resultado_validado', ...(permitirPrueba ? ['prueba_controlada'] : [])].includes(d.tipoEvidencia)) throw new Error('La comparación no está disponible.');
    if (!['cambio_agua_observado','sin_cambios','sin_datos'].includes(d.estado) || d.territorio?.alcance !== 'recorte_parcial' || typeof d.territorio.nombre !== 'string') throw new Error('No se pudo leer la comparación.');
    const fecha = f => typeof f === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(f) && new Date(f+'T00:00:00Z').toISOString().slice(0,10) === f;
    if (!fecha(d.comparacion?.referencia) || !fecha(d.comparacion?.comparacion) || d.comparacion.referencia >= d.comparacion.comparacion) throw new Error('Revisa las fechas.');
    const c=d.cobertura, r=d.resumen;
    if (!c || !r || !Number.isFinite(c.areaEstudiadaKm2) || c.areaEstudiadaKm2 <= 0 || !Number.isFinite(c.areaComparableKm2) || c.areaComparableKm2<0 || c.areaComparableKm2>c.areaEstudiadaKm2 || !Number.isFinite(c.fraccionComparable) || Math.abs(c.fraccionComparable-c.areaComparableKm2/c.areaEstudiadaKm2)>1e-8 || r.inundacionConfirmada !== null) throw new Error('No se pudo leer la cobertura.');
    const valores=['aguaPersistenteKm2','aguaNuevaKm2','aguaRetiradaKm2'].map(k=>r[k]);
    if (d.estado==='sin_datos' ? valores.some(v=>v!==null)||c.areaComparableKm2!==0 : valores.some(v=>!Number.isFinite(v)||v<0)||c.areaComparableKm2<=0||valores.reduce((a,b)=>a+b,0)>c.areaComparableKm2+1e-8||(d.estado==='sin_cambios')!==(valores[1]===0&&valores[2]===0)) throw new Error('El resumen no coincide con la cobertura.');
    if (d.capa?.type!=='FeatureCollection' || !Array.isArray(d.capa.features) || d.capa.features.length>500) throw new Error('No se pudo leer el mapa.');
    const ids=new Set();
    for (const f of d.capa.features) {
        const clase=f.properties?.clase;
        if (typeof f.id!=='string'||ids.has(f.id)||!Object.hasOwn(clasesAgua,clase)||f.geometry?.type!=='Polygon'||!Array.isArray(f.geometry.coordinates)||!f.geometry.coordinates.length||f.geometry.coordinates.length>20) throw new Error('No se pudo leer una ubicación.');
        ids.add(f.id);
        if ((d.estado==='sin_datos'&&clase!=='no_comparable')||(d.estado==='sin_cambios'&&['agua_nueva','agua_retirada'].includes(clase))) throw new Error('La capa no coincide con el resumen.');
        for (const a of f.geometry.coordinates) {
            if (!Array.isArray(a)||a.length<4||a.length>10000||JSON.stringify(a[0])!==JSON.stringify(a.at(-1))||a.some(p=>!Array.isArray(p)||p.length!==2||!p.every(Number.isFinite)||Math.abs(p[0])>180||Math.abs(p[1])>90)) throw new Error('No se pudo leer una ubicación.');
        }
    }
    if (typeof d.fuente?.nombre!=='string'||typeof d.fuente?.metodo!=='string'||!Number.isFinite(d.fuente?.resolucionMetros)) throw new Error('Falta la información de la comparación.');
    return d;
}

export function crearConsultaAgua(cargar, mostrar) {
    let secuencia=0, abortar=null;
    return async caso => {
        const turno=++secuencia;
        abortar?.abort(); abortar=new AbortController();
        mostrar({estado:'cargando'});
        try {
            const dato=await cargar(caso, abortar.signal);
            if(turno===secuencia) mostrar({estado:'resultado',dato:leerAgua(dato,true)});
        } catch(error) {
            if(turno===secuencia&&error.name!=='AbortError') mostrar({estado:'error',mensaje:'No se pudo cargar la comparación. Inténtalo otra vez.'});
        }
    };
}
