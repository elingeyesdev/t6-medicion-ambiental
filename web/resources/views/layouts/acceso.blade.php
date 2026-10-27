@extends('layouts.publico')
@section('clase-publica', 'pagina-acceso')
@section('contenido')
{{-- Composición oficial Tabler: sign-in-cover.html, formulario y cubierta. --}}
<div class="row g-0 flex-fill acceso-cover">
    <main class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center" id="contenido" tabindex="-1">
        <div class="container-tight my-5 px-4 px-lg-5">
            <div class="text-center mb-5"><a href="{{ route('inicio') }}" aria-label="Volver al inicio de GeoCruz"><x-panel.marca /></a></div>
            <h1 class="h2 text-center mb-4">@yield('encabezado')</h1>
            @yield('formulario')
            <div class="text-center mt-4"><a class="text-secondary" href="{{ route('inicio') }}">← Volver al inicio</a></div>
        </div>
    </main>
    <aside class="col-12 col-lg-6 col-xl-8 d-none d-lg-block acceso-cubierta">
        <img src="{{ asset('img/amboro/selva-amboro.jpg') }}" alt="Vegetación del Parque Nacional Amboró, Santa Cruz" width="1280" height="960">
        <div class="acceso-paisaje"><strong>Parque Nacional Amboró</strong><span>Santa Cruz, Bolivia</span><small><x-panel.credito-foto /></small></div>
    </aside>
</div>
@endsection
