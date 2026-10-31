@extends('layouts.app')
@section('titulo', 'Reportes')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/reportes.css') }}">@endpush
@section('contenido')
<div class="rep-contenido"><h1>Reportes ambientales</h1><p>Reúne las lecturas que necesitas y descárgalas en PDF o Excel.</p>@include('cuentas.estado')
@can('reportes.crear')
<section class="card"><div class="card-header"><h2>Preparar reporte</h2></div><form action="{{ route('reportes.guardar') }}" method="post" class="card-body rep-formulario">@csrf
    <div class="rep-ancho"><label class="form-label" for="rep-titulo">Título</label><input id="rep-titulo" class="form-control" name="titulo" maxlength="100" required value="{{ old('titulo','Reporte ambiental') }}"></div>
    <div><label class="form-label" for="rep-base">Datos del reporte</label><select id="rep-base" name="base" class="form-select"><option value="mediciones">Mediciones guardadas</option><option value="simulacion" @selected(old('base')==='simulacion')>Una simulación propia</option><option value="continua" @selected(old('base')==='continua')>Corte de la simulación global</option><option value="atmosfera" @selected(old('base')==='atmosfera')>Pronóstico atmosférico</option></select></div>
    <div><label class="form-label" for="rep-plantilla">Presentación</label><select id="rep-plantilla" name="plantilla" class="form-select"><option value="">Presentación estándar</option>@foreach($plantillas as $p)<option value="{{ $p->id }}" @selected(old('plantilla')===$p->id)>{{ $p->nombre }}</option>@endforeach</select></div>
    <fieldset class="rep-ancho rep-formulario" id="rep-mediciones"><legend>Elegir lecturas</legend>
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
        <label>Variables<select name="variables[]" class="form-select" multiple size="6" required>@foreach($variablesAtmosfera as $k=>$m)<option value="{{ $k }}" @selected(in_array($k,$elegidasVariables,true))>{{ $m[1] }} ({{ $k==='aqi'?'AQI':$m[2] }})</option>@endforeach</select></label>
        <label>Horas desde la hora actual<input type="number" name="horas" class="form-control" min="1" max="72" value="{{ old('horas',24) }}" required></label>
    </fieldset>
    <p class="rep-ancho" id="rep-continua" hidden>Guarda las cinco variables del último estado global. El reporte conserva su paso, revisión y ajustes manuales.</p>
    <div class="rep-ancho"><button class="btn btn-primary" type="submit">Generar reporte</button><a class="btn btn-outline-primary" href="{{ route('plantillas') }}">Mis plantillas</a></div>
</form></section>
@endcan
@include('simulaciones.continua')
<section class="card mt-4"><div class="card-header"><h2>Mis reportes</h2></div><div class="card-body">@forelse($reportes as $r)<a class="rep-guardado" href="{{ route('reportes.mostrar',$r->id) }}"><strong>{{ $r->titulo }}</strong><span>{{ $r->created_at->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</span></a>@empty<p>Todavía no tienes reportes guardados.</p>@endforelse{{ $reportes->links() }}</div></section>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/reportes/formulario.js') }}" defer></script><script type="module" src="{{ asset('js/simulaciones/continua.js') }}"></script>@endpush
