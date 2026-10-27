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
                <aside class="col-lg-6 acceso-foto d-none d-lg-block" aria-label="Parque Nacional Amboró">
                    <img src="{{ asset('img/amboro/selva-amboro.jpg') }}" alt="Selva del Parque Nacional Amboró, Santa Cruz" width="1280" height="960">
                    <span>Parque Nacional Amboró</span>
                </aside>
            </div>
        </div>
    </div>
</main>
<x-publico.pie />
@endsection
