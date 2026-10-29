@extends('layouts.app')
@section('titulo', 'Mis avisos')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/alertas.css') }}">@endpush
@section('contenido')
<div class="alertas-contenido"><h1>Mis avisos</h1>@include('cuentas.estado')
<p>{{ $sinLeer }} sin leer</p>
<details class="card mt-3"><summary class="card-header"><h2>Configurar avisos</h2></summary><div class="card-body">
    <p>Recibe avisos aquí sobre tu ubicación y sus alrededores (50 km). Sin ubicación guardada, recibirás avisos de todas las zonas.</p>
    <a href="{{ route('cuenta') }}">Elegir ubicación</a>
    <form action="{{ route('avisos.preferencias') }}" method="post" class="mt-4">@csrf @method('PUT')
        <input type="hidden" name="activos" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="activos" value="1" @checked(old('activos', $preferencias['activos']))> Recibir avisos dentro de GeoSur</label>
        <input type="hidden" name="simulados" value="0"><label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="simulados" value="1" @checked(old('simulados', $preferencias['simulados']))> Incluir simulaciones</label>
        <fieldset class="alertas-variables"><legend>Variables</legend>@foreach($metricas as $k => $m)<label><input class="form-check-input" type="checkbox" name="metricas[]" value="{{ $k }}" @checked(in_array($k, old('metricas', $preferencias['metricas']), true))> {{ $m['nombre'] }}</label>@endforeach</fieldset>
        <button class="btn btn-primary" type="submit">Guardar preferencias</button>
    </form>
</div></details>
@forelse($avisos as $aviso)
@php($a = $aviso->alerta)
<section class="card alerta-tarjeta"><div class="card-body"><h2>{{ $a->contenido['nombre'] }} · {{ $a->contenido['umbral']['nombre'] }}</h2>
    <p>{{ $metricas[$a->metrica]['nombre'] }}: {{ $a->contenido['valor'] }} {{ $metricas[$a->metrica]['unidad'] }} · {{ \App\Services\Datos\CatalogoAmbiental::ORIGENES[$a->origen] }}</p>
    <p>{{ $a->instante->setTimezone('America/La_Paz')->format('d/m/Y H:i') }} · Hora de Bolivia</p>
    <div class="alertas-acciones"><a class="btn btn-outline-primary" href="{{ route('alertas', ['metrica' => $a->metrica, 'origen' => $a->origen]) }}">Ver alertas</a>
    @unless($aviso->leido_el)<form method="post" action="{{ route('avisos.leer', $aviso->id) }}">@csrf @method('PATCH')<button class="btn btn-primary" type="submit">Marcar como leído</button></form>@else<span>Leído</span>@endunless</div>
</div></section>
@empty<p class="card card-body mt-4">No tienes avisos guardados.</p>@endforelse
{{ $avisos->links() }}</div>
@endsection
