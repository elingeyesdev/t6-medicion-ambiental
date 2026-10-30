<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="GeoSur: monitoreo ambiental y análisis del territorio.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Monitoreo ambiental') · {{ config('geocruz.nombre') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/marca/geosur-simbolo.svg') }}?v=gs-1">
    <link rel="preload" href="{{ asset('fonts/envirosense/space-grotesk-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/envirosense/fuentes.css') }}">
    <link rel="stylesheet" href="{{ asset('css/paginas/publico.css') }}?v=enviro-1">
    @stack('estilos')
</head>
<body class="pagina-publica @yield('clase-publica')">
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    @yield('contenido')
    @stack('scripts')
</body>
</html>
