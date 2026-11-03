@props(['nombre'])
@php
    $originales = ['map', 'report', 'users', 'bell', 'mail', 'flask'];
    $permitidos = ['world', 'layout-dashboard', 'map-pin', 'flame', 'cloud-rain', 'arrow-right', ...$originales];
    $icono = in_array($nombre, $permitidos, true) ? $nombre : 'world';
@endphp
<span {{ $attributes->class(['icono-panel']) }} aria-hidden="true">{!! file_get_contents(public_path((in_array($icono, $originales, true) ? 'img/envirosense/iconos/' : 'vendor/tabler-icons/').$icono.'.svg')) !!}</span>
