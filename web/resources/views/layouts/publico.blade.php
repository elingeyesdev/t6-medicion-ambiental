<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="GeoCruz: monitoreo ambiental de Santa Cruz, Bolivia.">
    <title>@yield('titulo', 'Monitoreo ambiental') · GeoCruz</title>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/panel/panel.css') }}">
    <link rel="stylesheet" href="{{ asset('css/panel/marca.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/marca/geocruz-simbolo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/paginas/publico.css') }}">
    @stack('estilos')
</head>
<body class="pagina-publica @yield('clase-publica')">
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    @yield('contenido')
</body>
</html>
