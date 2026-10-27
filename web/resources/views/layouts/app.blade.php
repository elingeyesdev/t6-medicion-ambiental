<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EnviroSense, adaptación GeoCruz para consultar información ambiental de Santa Cruz.">
    <title>@yield('titulo', 'Mapa de Monitoreo') · EnviroSense</title>
    <script src="{{ asset('js/panel/preferencias.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    @stack('estilos')
    <link rel="stylesheet" href="{{ asset('css/panel/panel.css') }}">
</head>
<body class="@yield('clase-cuerpo')">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="enviro-layout">
    <x-panel.menu :modulos="$modulos" />
    <button class="enviro-fondo" id="fondo-menu" type="button" aria-label="Cerrar menú" hidden></button>
    <div class="enviro-principal">
        <x-panel.cabecera />
        <main id="contenido" class="contenido-panel" tabindex="-1">@yield('contenido')</main>
        <x-panel.pie />
    </div>
</div>
<script src="{{ asset('vendor/tabler/tabler.min.js') }}" defer></script>
<script src="{{ asset('js/panel/navegacion.js') }}" defer></script>
@stack('scripts')
</body>
</html>
