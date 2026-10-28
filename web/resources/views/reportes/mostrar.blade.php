@extends('layouts.app')
@section('titulo', 'Reporte ambiental')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/reportes.css') }}">@endpush
@section('contenido')
<div class="rep-contenido"><a href="{{ route('reportes') }}">Volver a mis reportes</a><h1 class="mt-3">{{ $reporte->titulo }}</h1>@include('cuentas.estado')
<div class="rep-acciones"><a class="btn btn-primary" href="{{ route('reportes.descargar',[$reporte->id,'pdf']) }}">Descargar PDF</a><a class="btn btn-outline-primary" href="{{ route('reportes.descargar',[$reporte->id,'xlsx']) }}">Descargar Excel</a><span>{{ count($reporte->contenido['filas']) }} lecturas · Generado el {{ $reporte->created_at->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</span></div>
@if($reporte->parametros['base']==='simulacion')<p class="alert alert-warning mt-3">Este reporte contiene un escenario simulado, no observaciones ni un pronóstico.</p>@endif
@if(count($reporte->contenido['filas']) && $reporte->contenido['grafico'])
<section class="card mt-4"><div class="card-body"><label class="form-label" for="rep-serie">Evolución por zona y origen</label><select id="rep-serie" class="form-select"></select><svg id="rep-grafico" viewBox="0 0 800 260" role="img" aria-label="Evolución de las lecturas de la serie elegida"></svg><p id="rep-escala"></p><p id="rep-periodo"></p></div></section>
@endif
<section class="card mt-4"><div class="card-header"><h2>Resumen</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Zona y variable</th><th>Origen</th><th>Fuente</th><th>Lecturas</th><th>Mínimo</th><th>Máximo</th><th>Promedio</th></tr></thead><tbody>@forelse($reporte->contenido['resumen'] as $r)<tr><th>{{ $r['punto'] }} · {{ $r['variable'] }}</th><td>{{ \App\Services\Datos\CatalogoAmbiental::ORIGENES[$r['origen']] }}</td><td>{{ $r['fuenteNombre'] }}</td><td>{{ $r['cantidad'] }}</td><td>{{ $r['minimo'] }} {{ $r['unidad'] }}</td><td>{{ $r['maximo'] }} {{ $r['unidad'] }}</td><td>{{ $r['promedio'] }} {{ $r['unidad'] }}</td></tr>@empty<tr><td colspan="7">No hay lecturas para los filtros elegidos.</td></tr>@endforelse</tbody></table></div></section>
<section class="card mt-4"><div class="card-header"><h2>Lecturas incluidas</h2></div><div class="table-responsive rep-lecturas"><table class="table"><thead><tr>@foreach($reporte->contenido['columnas'] as $c)<th>{{ $columnas[$c] }}</th>@endforeach</tr></thead><tbody>@foreach($reporte->contenido['filas'] as $fila)<tr>@foreach($reporte->contenido['columnas'] as $c)<td>{{ \App\Services\Reportes\PrepararReporte::celda($fila,$c) }}</td>@endforeach</tr>@endforeach</tbody></table></div></section>
<p class="mt-3">{{ $reporte->contenido['nota'] }}</p>
</div>
@endsection
@push('scripts')<script id="datos-reporte" type="application/json">@json($reporte->contenido['filas'])</script><script type="module" src="{{ asset('js/reportes/grafico.js') }}"></script>@endpush

