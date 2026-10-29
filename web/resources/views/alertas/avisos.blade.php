@extends('layouts.app')
@section('titulo', 'Mis avisos')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/alertas.css') }}">@endpush
@section('contenido')
<div class="alertas-contenido"><h1>Mis avisos</h1>@include('cuentas.estado')
<p id="avisos-contador" role="status" aria-live="polite">{{ $sinLeer }} sin leer</p>
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
@include('alertas.canales')
@include('alertas.push')
<p id="avisos-actualizacion" role="status" aria-live="polite"></p>
<div id="avisos-lista" @if($avisos->currentPage() === 1 && \Illuminate\Support\Facades\Route::has('avisos.novedades')) data-novedades="{{ route('avisos.novedades') }}" @endif>
@include('alertas.lista-avisos')
</div>
<div id="avisos-paginacion">{{ $avisos->links() }}</div></div>
@endsection
@push('scripts')<script type="module" src="{{ asset('js/avisos/actualizar.js') }}"></script><script type="module" src="{{ asset('js/avisos/push.js') }}"></script>@endpush
