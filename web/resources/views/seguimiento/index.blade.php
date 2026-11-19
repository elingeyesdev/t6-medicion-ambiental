@extends('layouts.app')
@section('titulo', 'Seguimiento territorial')

@push('estilos')
<link rel="stylesheet" href="{{ asset('css/modulos/seguimiento.css') }}">
@endpush

@section('contenido')
@php
    $prioridades = [
        'prioritaria' => 'Revisión prioritaria',
        'seguimiento' => 'Seguimiento recomendado',
        'sin_senal' => 'Sin detecciones en el radio',
        'sin_cobertura' => 'Período sin cobertura',
    ];
    $fechaBolivia = fn ($valor) => \Carbon\CarbonImmutable::parse($valor)->setTimezone('America/La_Paz')->format('d/m/Y H:i');
@endphp
<div class="seguimiento">
    <header class="seguimiento-cabecera">
        <h1>Seguimiento territorial</h1>
        <p>Guarda una zona de Santa Cruz, revisa focos de calor cercanos y registra la verificación en campo.</p>
    </header>
    @if(session('estado'))
        <p class="alert alert-success" role="status">{{ session('estado') }}</p>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif
    <div class="seguimiento-grid">
        <aside class="seguimiento-zonas" aria-labelledby="zonas">
            <h2 id="zonas">Mis zonas</h2>
            <a class="btn btn-outline-secondary mb-3" href="{{ route('seguimiento.index') }}">Nueva zona</a>
            @forelse($zonas as $guardada)
                <a class="seguimiento-zona" href="{{ route('seguimiento.zona', $guardada->id) }}" @if($zona?->id === $guardada->id) aria-current="page" @endif>
                    <strong>{{ $guardada->nombre }}</strong>
                    <span>{{ $guardada->radio_km }} km de radio</span>
                </a>
            @empty
                <p>Todavía no tienes zonas guardadas.</p>
            @endforelse
            @if($zonas->hasPages())<div class="mt-3">{{ $zonas->links('pagination::bootstrap-5') }}</div>@endif
        </aside>
        <div class="seguimiento-contenido">
            @if($zona === null)
                <section class="seguimiento-tarjeta">
                    <h2>Guardar una zona</h2>
                    <p>Usa las coordenadas del centro de tu parcela o zona de vigilancia.</p>
                    <form method="post" action="{{ route('seguimiento.guardar') }}" class="seguimiento-formulario">
                        @csrf
                        <label>Nombre de la zona <input name="nombre" class="form-control" value="{{ old('nombre') }}" placeholder="Parcela San Miguel" maxlength="100" required></label>
                        <div class="seguimiento-coordenadas">
                            <label>Latitud <input name="latitud" class="form-control" type="number" step="any" min="-90" max="90" value="{{ old('latitud', $coordenadasIniciales['latitud'] ?? '') }}" placeholder="-17.78333" required></label>
                            <label>Longitud <input name="longitud" class="form-control" type="number" step="any" min="-180" max="180" value="{{ old('longitud', $coordenadasIniciales['longitud'] ?? '') }}" placeholder="-63.18120" required></label>
                            <label>Radio (km) <input name="radio_km" class="form-control" type="number" min="1" max="50" value="{{ old('radio_km', 10) }}" required></label>
                        </div>
                        <button class="btn btn-primary" type="submit">Guardar zona</button>
                    </form>
                </section>
            @else
                <section class="seguimiento-tarjeta" aria-labelledby="evaluar">
                    <h2 id="evaluar">{{ $zona->nombre }}</h2>
                    <p>{{ $zona->latitud }}, {{ $zona->longitud }} · Radio de {{ $zona->radio_km }} km</p>
                    <form class="seguimiento-consulta" method="post" action="{{ route('seguimiento.evaluar', $zona->id) }}">
                        @csrf
                        <label>Fecha final (opcional)
                            <input name="fecha" class="form-control" type="date" max="{{ $hoy }}" value="{{ old('fecha') }}">
                        </label>
                        <button class="btn btn-primary" type="submit">Evaluar últimos 7 días</button>
                    </form>
                    <p class="seguimiento-nota">Deja la fecha vacía para consultar hasta ahora. Las fechas se muestran en hora de Bolivia.</p>
                    @if($evaluacion !== null)
                        <div class="seguimiento-evaluacion" data-prioridad="{{ $evaluacion['prioridad'] }}">
                            <h3>{{ $prioridades[$evaluacion['prioridad']] }}</h3>
                            @if($evaluacion['detecciones'] !== null)
                                <dl>
                                    <div><dt>Detecciones cercanas</dt><dd>{{ $evaluacion['detecciones'] }}</dd></div>
                                    <div><dt>Confianza alta</dt><dd>{{ $evaluacion['confianzaAlta'] }}</dd></div>
                                    @if($evaluacion['distanciaKm'] !== null)
                                        <div><dt>Distancia mínima</dt><dd>{{ number_format($evaluacion['distanciaKm'], 2, ',', '.') }} km</dd></div>
                                    @endif
                                </dl>
                            @endif
                            <p>{{ $fechaBolivia($evaluacion['desde']) }} a {{ $fechaBolivia($evaluacion['hasta']) }} · {{ $evaluacion['fuente'] }} · {{ $evaluacion['producto'] }}</p>
                            @if($evaluacion['cobertura'] === 'parcial')<p>Cobertura parcial para el período consultado.</p>@endif
                            @if($evaluacion['prioridad'] === 'sin_cobertura')
                                <p>Elige otra fecha para evaluar.</p>
                            @elseif($evaluacion['detecciones'] === 0)
                                <p>La ausencia de detecciones no confirma que la zona esté libre de incendios.</p>
                            @else
                                <p>Las detecciones térmicas requieren verificación en campo.</p>
                            @endif
                        </div>
                    @endif
                </section>
                <section class="seguimiento-tarjeta">
                    <h2>Tareas de campo</h2>
                    @if($evaluacion !== null)
                        <form class="seguimiento-consulta" method="post" action="{{ route('seguimiento.tareas', $zona->id) }}">
                            @csrf
                            <label>Acción a realizar
                                <input name="titulo" class="form-control" value="{{ old('titulo') }}" placeholder="Verificar el acceso norte" maxlength="140" required>
                            </label>
                            <button class="btn btn-primary" type="submit">Crear tarea</button>
                        </form>
                    @else
                        <p>Evalúa esta zona para crear una tarea con el contexto de la consulta.</p>
                    @endif
                    @foreach($tareas as $tarea)
                        @php($contexto = json_decode($tarea->evaluacion, true))
                        <article class="seguimiento-tarea">
                            <h3>{{ $tarea->titulo }}</h3>
                            <p class="seguimiento-nota">{{ $prioridades[$contexto['prioridad']] }} · Consulta hasta {{ $fechaBolivia($contexto['hasta']) }}</p>
                            @if($tarea->completada_en !== null)
                                <p><strong>{{ $resultados[$tarea->resultado_tipo] }}</strong> · {{ $fechaBolivia($tarea->completada_en) }}</p>
                                <p class="seguimiento-resultado">{{ $tarea->resultado_nota }}</p>
                            @else
                                <details @if(old('tarea_formulario') == $tarea->id) open @endif>
                                    <summary>Registrar resultado</summary>
                                    <form method="post" class="seguimiento-formulario mt-3" action="{{ route('seguimiento.resultado', [$zona->id, $tarea->id]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="tarea_formulario" value="{{ $tarea->id }}">
                                        <label>Estado de la revisión
                                            <select name="resultado_tipo" class="form-select" required>
                                                <option value="">Elige un resultado</option>
                                                @foreach($resultados as $valor => $texto)
                                                    <option value="{{ $valor }}" @selected(old('tarea_formulario') == $tarea->id && old('resultado_tipo') === $valor)>{{ $texto }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>Qué encontraste
                                            <textarea name="resultado_nota" class="form-control" maxlength="1000" rows="3" required>{{ old('tarea_formulario') == $tarea->id ? old('resultado_nota') : '' }}</textarea>
                                        </label>
                                        <button class="btn btn-primary" type="submit">Guardar resultado</button>
                                    </form>
                                </details>
                            @endif
                        </article>
                    @endforeach
                    @if($tareas->hasPages())<div class="mt-3">{{ $tareas->links('pagination::bootstrap-5') }}</div>@endif
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
