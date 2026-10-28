@extends('layouts.app')
@section('titulo', 'Alertas ambientales')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/alertas.css') }}">@endpush
@section('contenido')
<div class="alertas-contenido">
    <h1>Alertas ambientales</h1>
    @include('cuentas.estado')
    <div class="alertas-acciones"><a class="btn btn-outline-primary" href="{{ route('umbrales') }}">Ver niveles</a><a class="btn btn-outline-primary" href="{{ route('avisos') }}">Mis avisos</a></div>
    <form method="get" class="card card-body mt-4 alertas-filtros">
        <label>Variable<select class="form-select" name="metrica"><option value="">Todas</option>@foreach($metricas as $k => $m)<option value="{{ $k }}" @selected(($filtros['metrica'] ?? '') === $k)>{{ $m['nombre'] }}</option>@endforeach</select></label>
        <label>Datos<select class="form-select" name="origen"><option value="">Registros y estimaciones</option>@foreach(\App\Services\Datos\CatalogoAmbiental::ORIGENES as $k => $n)<option value="{{ $k }}" @selected(($filtros['origen'] ?? '') === $k)>{{ $n }}</option>@endforeach</select></label>
        <label>Nivel<select class="form-select" name="severidad"><option value="">Todos</option>@foreach(['critica' => 'Crítica', 'emergencia' => 'Emergencia'] as $k => $n)<option value="{{ $k }}" @selected(($filtros['severidad'] ?? '') === $k)>{{ $n }}</option>@endforeach</select></label>
        <label>Estado<select class="form-select" name="reconocida"><option value="">Todas</option><option value="0" @selected(($filtros['reconocida'] ?? '') === '0')>Pendientes</option><option value="1" @selected(($filtros['reconocida'] ?? '') === '1')>Reconocidas</option></select></label>
        <label>Desde<input class="form-control" type="datetime-local" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
        <label>Hasta<input class="form-control" type="datetime-local" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
        <button class="btn btn-primary" type="submit">Consultar alertas</button>
    </form>
    <p class="mt-3">{{ $alertas->total() }} alertas · Hora de Bolivia</p>
    @forelse($alertas as $a)
    <section class="card alerta-tarjeta {{ $a->severidad === 'emergencia' ? 'alerta-emergencia' : '' }}">
        <div class="card-body">
            <div class="alertas-acciones"><h2>{{ $a->contenido['nombre'] }}</h2><span class="alerta-nivel">{{ $a->contenido['umbral']['nombre'] }}</span></div>
            <p>{{ $metricas[$a->metrica]['nombre'] }}: <strong>{{ $a->contenido['valor'] }} {{ $metricas[$a->metrica]['unidad'] }}</strong> · {{ \App\Services\Datos\CatalogoAmbiental::ORIGENES[$a->origen] }}</p>
            <p>{{ $a->instante->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</p>
            @if($a->origen === 'simulado')<p class="alert alert-warning">Escenario simulado. No es una observación ambiental.</p>@endif
            <div class="alertas-acciones">
                <a class="btn btn-outline-primary" href="{{ route('sensores', ['punto' => $a->contenido['punto'], 'metrica' => $a->metrica, 'origen' => $a->origen, 'desde' => $a->instante->subHour()->setTimezone('America/La_Paz')->format('Y-m-d\TH:i'), 'hasta' => $a->instante->addMinute()->lte(now()) ? $a->instante->addMinute()->setTimezone('America/La_Paz')->format('Y-m-d\TH:i') : null]) }}">Ver mediciones</a>
                @if($a->reconocida_el)<span>Reconocida el {{ $a->reconocida_el->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</span>
                @else
                    @can('alertas.gestionar')<form action="{{ route('alertas.reconocer', $a->id) }}" method="post">@csrf @method('PATCH')<button class="btn btn-primary" type="submit">Reconocer alerta</button></form>@else<span>Pendiente de revisión</span>@endcan
                @endif
            </div>
        </div>
    </section>
    @empty<p class="card card-body">No hay alertas para esta consulta.</p>@endforelse
    {{ $alertas->links() }}
</div>
@endsection
