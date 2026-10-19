<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="GeoCruz, información ambiental para Santa Cruz, sus provincias y municipios.">
    <title>@yield('titulo', 'Inicio') · GeoCruz</title>
    <link rel="stylesheet" href="{{ asset('css/geocruz.css') }}">
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="aplicacion">
    <aside class="lateral">
        <a class="marca" href="{{ route('inicio') }}" aria-label="GeoCruz, inicio">
            <span class="simbolo" aria-hidden="true">G<span>+</span></span>
            <span>GeoCruz<small>Información ambiental</small></span>
        </a>
        <p class="etiqueta menu-titulo">EXPLORAR</p>
        <nav aria-label="Navegación principal">
            <a class="enlace-menu {{ request()->routeIs('inicio') ? 'activo' : '' }}" href="{{ route('inicio') }}" @if(request()->routeIs('inicio')) aria-current="page" @endif>
                <span aria-hidden="true">⌂</span> Inicio
            </a>
            @foreach($modulos as $id => $item)
                <a class="enlace-menu {{ request()->route('modulo') === $id ? 'activo' : '' }}" href="{{ route('modulos.mostrar', $id) }}" @if(request()->route('modulo') === $id) aria-current="page" @endif>
                    <span class="numero-menu" aria-hidden="true">{{ $item['icono'] }}</span> {{ $item['nombre'] }}
                </a>
            @endforeach
        </nav>
        <div class="ambito"><span class="punto" aria-hidden="true"></span><strong>Santa Cruz, Bolivia</strong><p>Departamento, provincias y municipios.</p></div>
    </aside>
    <div class="principal">
        <header class="cabecera"><span>Observatorio ambiental</span><span class="insignia">Versión inicial</span></header>
        <main id="contenido" tabindex="-1">@yield('contenido')</main>
        <footer>GeoCruz · Santa Cruz, Bolivia <span>Información con contexto y procedencia.</span></footer>
    </div>
</div>
</body>
</html>
