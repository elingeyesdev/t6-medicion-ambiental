<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EnviroSense, adaptación GeoCruz para consultar información ambiental de Santa Cruz.">
    <title>@yield('titulo', 'Panel') · EnviroSense / GeoCruz</title>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    @stack('estilos')
    <link rel="stylesheet" href="{{ asset('css/panel/panel.css') }}">
</head>
<body class="@yield('clase-cuerpo')">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="page">
    <x-panel.menu :modulos="$modulos" />
    <div class="page-wrapper">
        <x-panel.cabecera />
        <main id="contenido" class="page-body container-fluid contenido-panel" tabindex="-1">@yield('contenido')</main>
        <x-panel.pie />
    </div>
</div>
<script src="{{ asset('vendor/tabler/tabler.min.js') }}" defer></script>
@stack('scripts')
</body>
</html>
