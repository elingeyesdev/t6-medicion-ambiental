@extends('layouts.app')
@section('titulo', 'Alertas ambientales')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/alertas.css') }}">@endpush
@section('contenido')
<div class="alertas-contenido">
    <header class="alertas-cabecera"><div><h1>Historial de <span>Alertas</span></h1><p>Consulta, filtra y reconoce las alertas ambientales generadas por el sistema.</p></div><span class="alertas-etiqueta">{{ $alertas->total() }} registros</span></header>
    @include('cuentas.estado')
    <nav class="alertas-pestanas" aria-label="Alertas y notificaciones"><a href="{{ route('alertas') }}" aria-current="page">Historial de alertas</a><a href="{{ route('umbrales') }}">Niveles ambientales</a><a href="{{ route('avisos') }}">Mis avisos</a></nav>
    <form method="get" class="alertas-filtros">
        <label>Desde<input class="form-control" type="datetime-local" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
        <label>Hasta<input class="form-control" type="datetime-local" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
        <label>Métrica<select class="form-select" name="metrica"><option value="">Todas las métricas</option>@foreach($metricas as $k => $m)<option value="{{ $k }}" @selected(($filtros['metrica'] ?? '') === $k)>{{ $m['nombre'] }}</option>@endforeach</select></label>
        <label>Severidad<select class="form-select" name="severidad"><option value="">Todas las severidades</option>@foreach(['critica' => 'Crítica', 'emergencia' => 'Emergencia'] as $k => $n)<option value="{{ $k }}" @selected(($filtros['severidad'] ?? '') === $k)>{{ $n }}</option>@endforeach</select></label>
        <label>Estado<select class="form-select" name="reconocida"><option value="">Todas</option><option value="0" @selected(($filtros['reconocida'] ?? '') === '0')>Pendientes</option><option value="1" @selected(($filtros['reconocida'] ?? '') === '1')>Reconocidas</option></select></label>
        <label>Datos<select class="form-select" name="origen"><option value="">Registros y estimaciones</option>@foreach(\App\Services\Datos\CatalogoAmbiental::ORIGENES as $k => $n)<option value="{{ $k }}" @selected(($filtros['origen'] ?? '') === $k)>{{ $n }}</option>@endforeach</select></label>
        <button class="btn btn-primary" type="submit">Consultar alertas</button>
    </form>
    <p class="alertas-nota">{{ $alertas->total() }} alertas · Hora de Bolivia</p>
    <div class="alertas-tabla-wrap table-responsive">
        @if($alertas->isEmpty())<div class="alertas-vacio">No se encontraron alertas con los filtros seleccionados.</div>
        @else
        <table class="alertas-tabla">
            <thead><tr><th scope="col">Fecha / Hora</th><th scope="col">Ciudad</th><th scope="col">Métrica</th><th scope="col">Valor</th><th scope="col">Nivel</th><th scope="col">Severidad</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead>
            <tbody>@foreach($alertas as $a)
                <tr class="{{ $a->reconocida_el ? 'alerta-reconocida' : '' }}">
                    <td class="alertas-fecha">{{ $a->instante->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                    <td><strong>{{ $a->contenido['nombre'] }}</strong></td>
                    <td>{{ $metricas[$a->metrica]['nombre'] }}<small>{{ \App\Services\Datos\CatalogoAmbiental::ORIGENES[$a->origen] }}</small>@if($a->origen === 'simulado')<small class="alertas-simulado">Escenario simulado. No es una observación ambiental.</small>@endif</td>
                    <td class="alertas-valor">{{ $a->contenido['valor'] }} <small>{{ $metricas[$a->metrica]['unidad'] }}</small></td>
                    <td>{{ $a->contenido['umbral']['nombre'] }}</td>
                    <td><span class="alerta-nivel {{ $a->severidad === 'emergencia' ? 'alerta-nivel-emergencia' : '' }}">{{ $a->severidad === 'emergencia' ? 'Emergencia' : 'Crítica' }}</span></td>
                    <td>@if($a->reconocida_el)<span class="alertas-reconocida">Reconocida</span><small>{{ $a->reconocida_el->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</small>
                    @else
                        @can('alertas.gestionar')<form action="{{ route('alertas.reconocer', $a->id) }}" method="post">@csrf @method('PATCH')<button class="btn btn-outline-primary" type="submit">Reconocer alerta</button></form>@else<span>Pendiente de revisión</span>@endcan
                    @endif</td>
                    <td><a class="btn btn-outline-primary" href="{{ route('sensores', ['punto' => $a->contenido['punto'], 'metrica' => $a->metrica, 'origen' => $a->origen, 'desde' => $a->instante->subHour()->setTimezone('America/La_Paz')->format('Y-m-d\TH:i'), 'hasta' => $a->instante->addMinute()->lte(now()) ? $a->instante->addMinute()->setTimezone('America/La_Paz')->format('Y-m-d\TH:i') : null]) }}">Ver mediciones</a></td>
                </tr>
            @endforeach</tbody>
        </table>
        @endif
    </div>
    <div class="alertas-paginacion">{{ $alertas->links() }}</div>
</div>
@endsection
