const base=document.getElementById('rep-base');
if(base){
    function cambiar(){
        for(const tipo of ['mediciones','simulacion','atmosfera','continua']){
            const seccion=document.getElementById('rep-'+tipo);
            seccion.hidden=base.value!==tipo;
            if(seccion.tagName==='FIELDSET') seccion.disabled=base.value!==tipo;
        }
        document.getElementById('rep-sesion').disabled=base.value!=='simulacion';
    }
    base.addEventListener('change',cambiar);cambiar();
}
