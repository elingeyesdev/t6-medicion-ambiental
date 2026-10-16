@extends('layouts.app')
@section('titulo', $modulo['nombre'])

@section('contenido')
<a class="volver" href="{{ route('inicio') }}">← Volver al inicio</a>
<div class="intro intro-modulo"><p class="etiqueta">SANTA CRUZ · INFORMACIÓN AMBIENTAL</p><h1>{{ $modulo['nombre'] }}</h1><p class="bajada">{{ $modulo['descripcion'] }}</p></div>
<section class="panel-pendiente" aria-labelledby="titulo-pendiente">
    <span class="numero-tarjeta grande" aria-hidden="true">{{ $modulo['icono'] }}</span>
    <p class="estado-pendiente">Próximamente</p>
    <h2 id="titulo-pendiente">Este módulo está en preparación</h2>
    <p>La consulta de datos aún no está disponible. Esta pantalla no representa una consulta sin resultados ni una ausencia del fenómeno.</p>
    <a class="boton" href="{{ route('inicio') }}">Explorar otros módulos</a>
</section>
@endsection
