@extends('layouts.publico')
@section('clase-publica', 'pagina-acceso')
@section('contenido')
<x-publico.cabecera />
<main id="contenido" tabindex="-1" class="acceso-cuerpo">
    <div class="container-xl">
        <div class="card acceso-panel">
            <div class="row g-0">
                <section class="col-12 col-lg-6 acceso-formulario">
                    <h1>@yield('encabezado')</h1>
                    @yield('formulario')
                </section>
                <aside class="col-lg-6 acceso-foto d-none d-lg-block" aria-label="Lomas de Arena">
                    <img src="{{ asset('img/lomas-arena/lomas-arena.jpg') }}" alt="Dunas y laguna de Lomas de Arena, Santa Cruz" width="1280" height="960">
                    <span>Lomas de Arena · Santa Cruz</span>
                </aside>
            </div>
        </div>
    </div>
</main>
<x-publico.pie fotografia="lomas" />
@endsection
