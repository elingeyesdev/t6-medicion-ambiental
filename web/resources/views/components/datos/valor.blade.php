@props(['metrica', 'valor', 'unidad', 'diferencia' => false])
@php
    $presentador = app(\App\Services\Datos\UnidadesPresentacion::class);
    $preferidas = $presentador->preferencias(auth()->user());
@endphp
<span data-medida="{{ $metrica }}" data-valor="{{ is_int($valor) || is_float($valor) ? $valor : '' }}" data-unidad="{{ $unidad }}" @if($diferencia) data-diferencia="true" @endif>{{ $presentador->texto($metrica, $valor, $unidad, $preferidas, $diferencia) }}</span>
