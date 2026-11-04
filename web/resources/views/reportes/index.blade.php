@extends('layouts.app')
@section('titulo', 'Reportes')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/reportes.css') }}">@endpush
@section('contenido')
@php($vista = in_array(request('vista'), ['dashboard', 'atmosfera', 'historial'], true) ? request('vista') : (old('base') === 'atmosfera' ? 'atmosfera' : 'dashboard'))
<div class="rep-contenido">
<header class="rep-cabecera"><h1>Reportes <span>Ambientales</span></h1><p>Explora el historial de lecturas por localidad, visualiza tendencias y conserva tus reportes ambientales.</p></header>
@include('cuentas.estado')
<nav class="rep-pestanas" aria-label="Área de reportes">
    <a href="{{ route('reportes', ['vista' => 'dashboard']) }}" @if($vista === 'dashboard') aria-current="page" @endif>Dashboard Global</a>
    <a href="{{ route('reportes', ['vista' => 'atmosfera']) }}" @if($vista === 'atmosfera') aria-current="page" @endif>Pronóstico Atmosférico</a>
    <a href="{{ route('reportes', ['vista' => 'historial']) }}" @if($vista === 'historial') aria-current="page" @endif>Historial y Simulaciones</a>
    <a href="{{ route('plantillas') }}">Plantillas</a>
</nav>
@if($vista === 'dashboard')
    @include('reportes.dashboard')
@elseif($vista === 'historial')
    <div class="rep-kpis">
        <article><span>Reportes guardados</span><strong>{{ $reportes->total() }}</strong></article>
        <article><span>Plantillas disponibles</span><strong>{{ $plantillas->count() }}</strong></article>
        <article><span>Simulaciones recientes</span><strong>{{ $simulaciones->count() }}</strong><small>Hasta las últimas 100</small></article>
    </div>
@endif
@can('reportes.crear')
<section class="card rep-generador" id="rep-preparar"><div class="card-header"><div><h2>{{ $vista === 'atmosfera' ? 'Pronóstico atmosférico por localidad' : 'Preparar reporte' }}</h2><p class="rep-subtitulo">Selecciona las localidades, variables y el período de tu consulta.</p></div></div><form action="{{ route('reportes.guardar') }}" method="post" class="card-body rep-formulario">@csrf
    <div class="rep-ancho"><label class="form-label" for="rep-titulo">Título</label><input id="rep-titulo" class="form-control" name="titulo" maxlength="100" required value="{{ old('titulo','Reporte ambiental') }}"></div>
    <div><label class="form-label" for="rep-base">Datos del reporte</label><select id="rep-base" name="base" class="form-select"><option value="mediciones">Mediciones guardadas</option><option value="simulacion" @selected(old('base')==='simulacion')>Una simulación propia</option><option value="continua" @selected(old('base')==='continua')>Corte de la simulación global</option><option value="atmosfera" @selected(old('base', $vista === 'atmosfera' ? 'atmosfera' : 'mediciones')==='atmosfera')>Pronóstico atmosférico</option></select></div>
    <div><label class="form-label" for="rep-plantilla">Presentación</label><select id="rep-plantilla" name="plantilla" class="form-select"><option value="">Presentación estándar</option>@foreach($plantillas as $p)<option value="{{ $p->id }}" @selected(old('plantilla')===$p->id)>{{ $p->nombre }}</option>@endforeach</select></div>
    <fieldset class="rep-ancho rep-formulario" id="rep-mediciones"><legend>Filtro de localidades y variables</legend>
        <div><label class="form-label" for="rep-pais">País o territorio</label><select id="rep-pais" name="pais" class="form-select"><option value="">Todos</option>@foreach($paises as $p)<option value="{{ $p['codigo'] }}" @selected(old('pais')===$p['codigo'])>{{ $p['nombre'] }}</option>@endforeach</select></div>
        <div><label class="form-label" for="rep-punto">Zona</label><select id="rep-punto" name="punto" class="form-select"><option value="">Todas las zonas</option>@foreach($puntos as $p)<option value="{{ $p->codigo }}" @selected(old('punto')===$p->codigo)>{{ $p->nombre }} · {{ $p->pais }}</option>@endforeach</select></div>
        <div><label class="form-label" for="rep-metrica">Variable</label><select id="rep-metrica" name="metrica" class="form-select"><option value="">Todas</option>@foreach($metricas as $k=>$m)<option value="{{ $k }}" @selected(old('metrica')===$k)>{{ $m['nombre'] }}</option>@endforeach</select></div>
        <div><label class="form-label" for="rep-origen">Origen de los datos</label><select id="rep-origen" name="origen" class="form-select"><option value="">Registrados y estimados</option>@foreach(['observado'=>'Solo registrados','estimado'=>'Solo estimaciones','simulado'=>'Solo simulados','todos'=>'Todos, incluidos los simulados'] as $k=>$n)<option value="{{ $k }}" @selected(old('origen')===$k)>{{ $n }}</option>@endforeach</select></div>
        <div><label class="form-label" for="rep-desde">Desde · hora de Bolivia</label><input id="rep-desde" name="desde" type="datetime-local" class="form-control" value="{{ old('desde',now('America/La_Paz')->subWeek()->format('Y-m-d\TH:i')) }}" max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}"></div>
        <div><label class="form-label" for="rep-hasta">Hasta · hora de Bolivia</label><input id="rep-hasta" name="hasta" type="datetime-local" class="form-control" value="{{ old('hasta',now('America/La_Paz')->format('Y-m-d\TH:i')) }}" max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}"></div>
    </fieldset>
    <div class="rep-ancho" id="rep-simulacion"><label class="form-label" for="rep-sesion">Simulación guardada</label><select id="rep-sesion" name="simulacion" class="form-select"><option value="">Elegir simulación</option>@foreach($simulaciones as $s)<option value="{{ $s->id }}" @selected(old('simulacion')===$s->id)>{{ $s->nombre }}</option>@endforeach</select></div>
    <fieldset class="rep-ancho rep-formulario" id="rep-atmosfera" hidden disabled><legend>Pronóstico por localidad</legend>
        @php($elegidosAtmosfera = is_array(old('puntos',['santacruz'])) ? old('puntos',['santacruz']) : [])
        @php($elegidasVariables = is_array(old('variables',['temperatura','lluvia','viento','visibilidad','nieve','nieve_acumulada','aqi'])) ? old('variables',['temperatura','lluvia','viento','visibilidad','nieve','nieve_acumulada','aqi']) : [])
        <p class="rep-ancho">Consulta estimaciones por hora y conserva este corte. Hasta mil valores entre todas las localidades y variables.</p>
        <label>Localidades<select name="puntos[]" class="form-select" multiple size="6" required>@foreach($puntosAtmosfera as $p)<option value="{{ $p['codigo'] }}" @selected(in_array($p['codigo'],$elegidosAtmosfera,true))>{{ $p['nombre'] }} · {{ $p['pais'] }}</option>@endforeach</select></label>
        <label>Variables<select name="variables[]" class="form-select" multiple size="6" required>@foreach($variablesAtmosfera as $k=>$m)<option value="{{ $k }}" @selected(in_array($k,$elegidasVariables,true))>{{ $m[1] }} ({{ $m[2] }})</option>@endforeach</select></label>
        <label>Horas desde la hora actual<input type="number" name="horas" class="form-control" min="1" max="72" value="{{ old('horas',24) }}" required></label>
    </fieldset>
    <p class="rep-ancho" id="rep-continua" hidden>Guarda las cinco variables del último estado global. El reporte conserva su paso, revisión y ajustes manuales.</p>
    <div class="rep-ancho"><button class="btn btn-primary" type="submit">Generar reporte</button><a class="btn btn-outline-primary" href="{{ route('plantillas') }}">Mis plantillas</a></div>
</form></section>
@endcan
@if($vista === 'historial')
@include('simulaciones.continua')
@endif
<section class="card mt-4" id="rep-historial"><div class="card-header rep-fila"><div><h2>Mis reportes</h2><p class="rep-subtitulo">{{ $reportes->total() }} reportes guardados · Hora de Bolivia</p></div><a href="{{ route('plantillas') }}" class="btn btn-outline-primary">Mis plantillas</a></div>
<div class="table-responsive"><table class="table rep-tabla"><thead><tr><th scope="col">Reporte</th><th scope="col">Fecha / Hora</th><th scope="col">Descargas</th><th scope="col">Detalle</th></tr></thead><tbody>
@forelse($reportes as $r)<tr><td><a class="rep-enlace" href="{{ route('reportes.mostrar',$r->id) }}">{{ $r->titulo }}</a></td><td>{{ $r->created_at->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</td><td><div class="rep-acciones"><a class="btn rep-exportar-pdf" href="{{ route('reportes.descargar',[$r->id,'pdf']) }}">PDF</a><a class="btn btn-outline-primary" href="{{ route('reportes.descargar',[$r->id,'xlsx']) }}">Excel</a></div></td><td><a href="{{ route('reportes.mostrar',$r->id) }}">Ver reporte</a></td></tr>
@empty<tr><td colspan="4" class="rep-vacio">Todavía no tienes reportes guardados.</td></tr>@endforelse
</tbody></table></div><div class="rep-paginacion">{{ $reportes->appends(['vista' => $vista])->links() }}</div></section>
</div>
@endsection
@push('scripts')@if($vista === 'dashboard' && $reportes->first() && count($reportes->first()->contenido['filas']))<script id="datos-reporte" type="application/json">@json([...$reportes->first()->contenido['filas'],...($reportes->first()->contenido['faltantes'] ?? [])])</script><script type="module" src="{{ asset('js/reportes/grafico.js') }}"></script>@endif<script src="{{ asset('js/reportes/formulario.js') }}" defer></script><script type="module" src="{{ asset('js/simulaciones/continua.js') }}"></script>@endpush
