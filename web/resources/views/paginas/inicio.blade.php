@extends('layouts.publico')
@section('titulo', 'Inicio')
@section('clase-publica', 'pagina-inicio')
@section('contenido')
<header class="portada-cabecera">
    <a href="{{ route('inicio') }}" aria-label="EnviroSense, inicio"><x-panel.marca /></a>
    <nav aria-label="Navegación de inicio">
        <a href="#modulos">Módulos</a>
        <a href="{{ route('mapa') }}">Mapa</a>
        @auth <a class="btn btn-primary" href="{{ route('mapa') }}">Ir al mapa</a>
        @else <a class="btn btn-primary" href="{{ route('login') }}">Ingresar</a> @endauth
    </nav>
</header>
<main id="contenido" tabindex="-1">
    <section class="portada-hero">
        <div class="portada-presentacion">
            <p class="etiqueta">Santa Cruz · Bolivia</p>
            <h1>Monitoreo<br>ambiental.</h1>
            <p>Focos de calor y sequías por provincia y municipio.</p>
            <div class="portada-acciones"><a class="btn btn-primary" href="{{ route('mapa') }}">Entrar al mapa <x-panel.icono nombre="arrow-right" /></a>@guest <a class="btn btn-outline-light" href="{{ route('register') }}">Crear cuenta</a> @endguest</div>
        </div>
        <figure class="portada-mapa"><img src="{{ asset('img/envirosense/santa-cruz.svg') }}" alt="Mapa de referencia de las provincias de Santa Cruz" width="560" height="610"><figcaption>Santa Cruz · límites de referencia OCHA/HDX</figcaption></figure>
    </section>
    <section class="portada-modulos" id="modulos" aria-labelledby="titulo-modulos">
        <h2 id="titulo-modulos">Módulos</h2>
        <div class="row g-3">
            @foreach(config('geocruz.modulos') as $id => $modulo)
                @if($modulo['habilitado'])
                    <div class="col-md-6"><a class="card modulo-publico" href="{{ route('modulos.mostrar', $id) }}" aria-label="Abrir {{ $modulo['nombre'] }}"><div class="card-body"><x-panel.icono :nombre="$modulo['icono']" /><div><h3>{{ $modulo['nombre'] }}</h3><p>{{ $id === 'focos-calor' ? 'NASA FIRMS · VIIRS' : 'ERA5 · SPI-3' }}</p></div><x-panel.icono nombre="arrow-right" /></div></a></div>
                @endif
            @endforeach
        </div>
    </section>
</main>
<footer class="portada-pie"><span>EnviroSense · Santa Cruz, Bolivia</span><span>Límites: OCHA/HDX · CC BY 3.0 IGO</span></footer>
@endsection
