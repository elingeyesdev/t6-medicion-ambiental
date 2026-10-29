@props(['metrica', 'unidad'])
@php($presentador = app(\App\Services\Datos\UnidadesPresentacion::class))
<span data-simbolo="{{ $metrica }}" data-unidad="{{ $unidad }}">{{ $presentador->unidad($metrica, $unidad, $presentador->preferencias(auth()->user())) }}</span>
