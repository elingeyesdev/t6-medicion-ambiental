@extends('layouts.publico')
@section('clase-publica', 'pagina-acceso')
@section('contenido')
<x-publico.cabecera />
<main id="contenido" tabindex="-1" class="acceso-cuerpo">
    <img class="acceso-fondo" src="{{ asset('img/lomas-arena/lomas-arena.jpg') }}" alt="" aria-hidden="true" width="1280" height="960" fetchpriority="high">
    <div class="container-xl">
        <section class="card acceso-panel acceso-formulario">
            <h1>@yield('encabezado')</h1>
            @yield('formulario')
        </section>
    </div>
    <span class="acceso-lugar"><x-panel.icono nombre="map-pin" /> Lomas de Arena · Santa Cruz</span>
</main>
<x-publico.pie fotografia="lomas" />
@endsection
