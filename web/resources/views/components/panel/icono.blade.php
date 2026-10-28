@props(['nombre'])
@php
    $permitidos = ['world', 'layout-dashboard', 'map-pin', 'flame', 'cloud-rain', 'arrow-right'];
    $icono = in_array($nombre, $permitidos, true) ? $nombre : 'world';
@endphp
<span {{ $attributes->class(['icono-panel']) }} aria-hidden="true">{!! file_get_contents(public_path('vendor/tabler-icons/'.$icono.'.svg')) !!}</span>
