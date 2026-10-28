@extends('layouts.publico')
@section('clase-publica', 'pagina-acceso')
@section('contenido')
<div class="acceso-distribucion">
    <aside class="acceso-referencia">
        <a href="{{ route('inicio') }}" aria-label="Volver al inicio de EnviroSense"><x-panel.marca /></a>
        <img src="{{ asset('img/envirosense/santa-cruz.svg') }}" alt="Provincias de Santa Cruz" width="560" height="610">
        <div><p class="etiqueta">Santa Cruz · Bolivia</p><h2>Observación ambiental</h2><p>Mapa, datos y consultas.</p></div>
    </aside>
    <main class="acceso-formulario" id="contenido" tabindex="-1">
        <div class="acceso-caja">
            <a class="acceso-volver" href="{{ route('inicio') }}">← Inicio</a>
            <h1>@yield('encabezado')</h1>
            @yield('formulario')
        </div>
    </main>
</div>
@endsection
