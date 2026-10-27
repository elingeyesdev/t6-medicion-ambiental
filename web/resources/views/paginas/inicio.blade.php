@extends('layouts.publico')
@section('titulo', 'Inicio')
@section('clase-publica', 'pagina-inicio')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/inicio.css') }}">
@endpush
@section('contenido')
<header class="portada-cabecera">
    <a href="{{ route('inicio') }}" aria-label="GeoCruz, inicio"><x-panel.marca /></a>
    <nav aria-label="Navegación de inicio">
        <a href="#explorar">Qué puedes consultar</a>
        @auth
            <a class="btn btn-primary" href="{{ route('mapa') }}">Ir al mapa</a>
        @else
            <a class="btn btn-primary" href="{{ route('login') }}">Ingresar</a>
        @endauth
    </nav>
</header>
<main id="contenido" tabindex="-1">
    <section class="portada-hero">
        <img class="portada-paisaje" src="{{ asset('img/amboro/selva-amboro.jpg') }}" alt="Selva del Parque Nacional Amboró, Santa Cruz" width="1280" height="960" fetchpriority="high">
        <div class="container-xl portada-presentacion">
            <h1>Explora<br>Santa Cruz.</h1>
            <p>Focos de calor y sequías.<br>Consulta tu provincia o municipio en el mapa.</p>
            <div class="portada-acciones">
                <a class="btn btn-primary btn-lg" href="{{ route('mapa') }}">Entrar al mapa <x-panel.icono nombre="arrow-right" /></a>
                @guest
                    <a class="btn btn-outline-light btn-lg" href="{{ route('register') }}">Crear cuenta</a>
                @endguest
            </div>
        </div>
        <div class="portada-lugar"><x-panel.icono nombre="map-pin" /><span>Parque Nacional Amboró</span></div>
    </section>
    <section class="container-xl portada-modulos" id="explorar" aria-labelledby="titulo-explorar">
        <h2 id="titulo-explorar">Qué quieres consultar</h2>
        <div class="row g-4">
            @foreach(config('geocruz.modulos') as $id => $modulo)
                @if($modulo['habilitado'])
                    <div class="col-md-6">
                        <a class="card modulo-publico consulta-{{ $id }}" href="{{ route('modulos.mostrar', $id) }}" aria-label="Abrir {{ $modulo['nombre'] }}">
                            <div class="card-body">
                                <span class="consulta-icono"><x-panel.icono :nombre="$modulo['icono']" /></span>
                                <div>
                                    <h3>{{ $modulo['nombre'] }}</h3>
                                    <p>{{ $id === 'focos-calor' ? 'Ubica las detecciones de calor por fecha y lugar.' : 'Revisa las condiciones de lluvia de cada zona.' }}</p>
                                    <span class="consulta-enlace">
                                        {{ $id === 'focos-calor' ? 'Ver focos de calor' : 'Consultar sequías' }} <x-panel.icono nombre="arrow-right" />
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
</main>
<footer class="portada-pie">
    <span>GeoCruz · Santa Cruz, Bolivia</span>
    <details class="portada-credito">
        <summary>Sobre la fotografía</summary>
        <p>Parque Nacional Amboró · <x-panel.credito-foto /></p>
    </details>
</footer>
@endsection
