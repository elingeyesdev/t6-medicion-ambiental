@php($ultimo = $reportes->first())
<section class="card rep-dashboard-cabecera">
    <div class="card-body rep-fila"><div><h2>Dashboard Analítico Global</h2><p class="rep-subtitulo">{{ $ultimo ? 'Último corte guardado: '.$ultimo->titulo : 'Análisis de tus lecturas ambientales por localidad' }}</p></div>
        <div class="rep-acciones">
            @if($ultimo)<a class="btn rep-exportar-pdf" href="{{ route('reportes.descargar',[$ultimo->id,'pdf']) }}">Descargar PDF</a><a class="btn btn-primary" href="{{ route('reportes.descargar',[$ultimo->id,'xlsx']) }}">Exportar Excel</a>
            @else @can('reportes.crear')<a class="btn btn-primary" href="#rep-preparar">Preparar reporte</a>@endcan @endif
        </div>
    </div>
</section>
@if($ultimo && count($ultimo->contenido['filas']))
    <div class="rep-kpis">
        <article><span>Lecturas del corte</span><strong>{{ count($ultimo->contenido['filas']) }}</strong></article>
        <article><span>Series ambientales</span><strong>{{ count($ultimo->contenido['resumen']) }}</strong></article>
        <article><span>Reportes guardados</span><strong>{{ $reportes->total() }}</strong></article>
    </div>
    @if(in_array($ultimo->parametros['base'], ['simulacion','continua']))<p class="alert alert-warning">Este corte contiene un escenario simulado, no observaciones ni un pronóstico.</p>@endif
    @if(isset($ultimo->contenido['atribucion']))<p class="rep-nota">{{ $ultimo->contenido['atribucion'] }} <a href="https://open-meteo.com/">Fuente del pronóstico</a>.</p>@endif
    <section class="card rep-dashboard-grafico"><div class="card-header"><h2>Evolución comparativa por localidad</h2></div><div class="card-body">
        <div class="rep-formulario"><div><label class="form-label" for="rep-serie">Filtro de localidades, variables y origen</label><select id="rep-serie" class="form-select"></select></div><div><label class="form-label" for="rep-tipo">Visualización</label><select id="rep-tipo" class="form-select"><option value="linea">Línea de tiempo</option><option value="barras">Barras</option></select></div></div>
        <svg id="rep-grafico" viewBox="0 0 800 260" role="img" aria-label="Evolución de las lecturas de la serie elegida"></svg><p id="rep-escala" class="rep-nota"></p><p id="rep-periodo" class="rep-nota"></p>
    </div></section>
    <section class="card rep-dashboard-matriz"><div class="card-header"><h2>Matriz de Datos Consolidados</h2></div><div class="table-responsive"><table class="table rep-tabla"><thead><tr><th scope="col">Localidad / Variable</th><th scope="col">Origen</th><th scope="col">Lecturas</th><th scope="col">Mínimo</th><th scope="col">Máximo</th><th scope="col">Promedio</th></tr></thead><tbody>
    @foreach($ultimo->contenido['resumen'] as $serie)<tr><td><strong>{{ $serie['punto'] }}</strong><small>{{ $serie['variable'] }} · {{ $serie['fuenteNombre'] }}</small></td><td>{{ \App\Services\Datos\CatalogoAmbiental::ORIGENES[$serie['origen']] }}</td><td>{{ $serie['cantidad'] }}</td><td>{{ $serie['minimo'] }} {{ $serie['unidad'] }}</td><td>{{ $serie['maximo'] }} {{ $serie['unidad'] }}</td><td>{{ $serie['promedio'] }} {{ $serie['unidad'] }}</td></tr>@endforeach
    </tbody></table></div><div class="card-body rep-nota">{{ $ultimo->contenido['nota'] }}</div></section>
@else
    <section class="card rep-dashboard-grafico"><div class="card-header"><h2>Evolución comparativa por localidad</h2></div><div class="rep-vacio">{{ $ultimo ? 'El último corte no contiene lecturas para representar. Genera un reporte con otros filtros.' : 'Genera tu primer reporte para visualizar las series y su matriz de datos.' }}</div></section>
@endif
