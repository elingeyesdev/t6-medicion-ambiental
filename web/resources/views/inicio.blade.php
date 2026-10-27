@extends('layouts.app')
@section('titulo', 'Inicio')

@section('contenido')
<div class="intro">
    <p class="etiqueta">UN TERRITORIO, CINCO PERSPECTIVAS</p>
    <h1>Conoce el entorno<br>de Santa Cruz.</h1>
    <p class="bajada">Un espacio para consultar información ambiental del departamento y sus provincias y municipios.</p>
</div>
<section class="aviso" aria-labelledby="estado-inicial">
    <span class="icono-aviso" aria-hidden="true">i</span>
    <div><h2 id="estado-inicial">Explora focos de calor y sequías</h2><p>Consulta detecciones térmicas de NASA FIRMS e indicadores SPI-3 derivados de ERA5. Sequías muestra únicamente las muestras preparadas y su cobertura; los otros tres módulos siguen en preparación.</p></div>
</section>
<section aria-labelledby="titulo-modulos">
    <div class="titulo-seccion"><h2 id="titulo-modulos">Explora los módulos</h2><span>Información ambiental por tema</span></div>
    <div class="tarjetas">
        @foreach($modulos as $id => $modulo)
            <a class="tarjeta" href="{{ route('modulos.mostrar', $id) }}">
                <div class="encabezado-tarjeta"><span class="numero-tarjeta" aria-hidden="true">{{ $modulo['icono'] }}</span><span class="estado-pendiente">{{ $id === 'focos-calor' ? 'Consulta disponible' : ($id === 'sequias' ? 'Cobertura por muestras' : 'Próximamente') }}</span></div>
                <h3>{{ $modulo['nombre'] }}</h3><p>{{ $modulo['descripcion'] }}</p>
                <span class="accion">{{ in_array($id, ['focos-calor', 'sequias'], true) ? 'Consultar '.$modulo['nombre'] : 'Acerca del módulo' }} <span aria-hidden="true">↗</span></span>
            </a>
        @endforeach
        <div class="tarjeta tarjeta-contexto"><span class="etiqueta">EL CONTEXTO IMPORTA</span><h3>Cada dato tiene<br>una historia.</h3><p>Su fuente, fecha, unidad y cobertura acompañarán a los resultados para interpretarlos correctamente.</p></div>
    </div>
</section>
@endsection
