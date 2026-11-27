@extends('layouts.app')
@section('titulo', 'Seguimiento territorial')

@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/mapas/centrar-seleccion.css') }}">
<link rel="stylesheet" href="{{ asset('css/modulos/seguimiento.css') }}?v=vigilancia-5">
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
        <p>Elige una zona de Santa Cruz en el mapa y revisa las detecciones de calor dentro de su radio.</p>
    </header>
    @if(session('estado'))
        <p class="alert alert-success" role="status">{{ session('estado') }}</p>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif
    <div class="seguimiento-grid{{ $zona ? ' seguimiento-grid-con-zona' : '' }}">
        <aside class="seguimiento-zonas" aria-labelledby="zonas">
            <h2 id="zonas">Mis zonas</h2>
            @if($zona !== null)<a class="btn btn-outline-secondary mb-3" href="{{ route('seguimiento.index') }}">Nueva zona</a>@endif
            @forelse($zonas as $guardada)
                <a class="seguimiento-zona" href="{{ route('seguimiento.zona', $guardada->id) }}" @if($zona?->id === $guardada->id) aria-current="page" @endif>
                    <strong>{{ $guardada->nombre }}</strong>
                    @php
                        $manual = json_decode($guardada->evaluacion ?? 'null', true);
                        $vigilancia = \App\Services\Seguimiento\EvaluarZona::ultimaVigilancia($guardada->id);
                        $ultima = ($vigilancia['hasta'] ?? '') >= ($manual['hasta'] ?? '') ? ($vigilancia ?? $manual) : $manual;
                    @endphp
                    <span>{{ $guardada->radio_km }} km de radio</span>
                    @if(($ultima['detecciones'] ?? 0) > 0)
                        <span class="seguimiento-senal">Aviso · {{ $ultima['detecciones'] }} {{ $ultima['detecciones'] === 1 ? 'foco en el radio' : 'focos en el radio' }}</span>
                    @else
                        <span>{{ $prioridades[$ultima['prioridad'] ?? ''] ?? 'Sin evaluar' }}</span>
                    @endif
                    @if(isset($ultima['hasta']))<span>Consulta hasta {{ $fechaBolivia($ultima['hasta']) }}</span>@endif
                </a>
            @empty
                <p>Todavía no tienes zonas guardadas.</p>
            @endforelse
            @if($zonas->hasPages())<div class="mt-3">{{ $zonas->links('pagination::bootstrap-5') }}</div>@endif
        </aside>
        <div class="seguimiento-contenido">
            @if($zona === null)
                <section class="seguimiento-tarjeta">
                    <h2>{{ $fechaInicial ? 'Evaluar alrededor de la detección' : 'Guardar una zona' }}</h2>
                    <p>Elige el centro en el mapa o escribe las coordenadas. Ajusta el radio que quieres vigilar.</p>
                    <form method="post" action="{{ route('seguimiento.guardar') }}" class="seguimiento-formulario">
                        @csrf
                        <label>Nombre de la zona <input name="nombre" class="form-control" value="{{ old('nombre') }}" placeholder="Mi finca" maxlength="100" required></label>
                        @if($fechaInicial)<input type="hidden" name="fecha" value="{{ $fechaInicial }}"><p class="seguimiento-nota mb-0">Se buscarán detecciones en los siete días hasta el {{ \Carbon\CarbonImmutable::parse($fechaInicial)->format('d/m/Y') }}, fecha de la detección.</p>@endif
                        <div class="seguimiento-mapa" data-mapa-zona data-editar aria-label="Mapa para elegir el centro de la zona"></div>
                        <div class="seguimiento-coordenadas">
                            <label>Latitud <input name="latitud" class="form-control" type="number" step="any" min="-90" max="90" value="{{ old('latitud', $coordenadasIniciales['latitud'] ?? '') }}" placeholder="-17.78333" required></label>
                            <label>Longitud <input name="longitud" class="form-control" type="number" step="any" min="-180" max="180" value="{{ old('longitud', $coordenadasIniciales['longitud'] ?? '') }}" placeholder="-63.18120" required></label>
                            <label>Radio (km) <input name="radio_km" class="form-control" type="number" min="1" max="50" value="{{ old('radio_km', 10) }}" required></label>
                        </div>
                        <button class="btn btn-primary" type="submit" name="evaluar" value="1">Guardar zona y buscar focos</button>
                    </form>
                </section>
            @else
                <section class="seguimiento-tarjeta" aria-labelledby="evaluar">
                    <header class="zona-encabezado">
                        <div><h2 id="evaluar">{{ $zona->nombre }}</h2><p>Radio de vigilancia de {{ $zona->radio_km }} km</p></div>
                        <button class="btn btn-outline-secondary" type="button" data-actualizar-zona hidden>Actualizar ahora</button>
                    </header>
                    @include('seguimiento.vigilancia')
                    <div id="mapa-zona" class="seguimiento-mapa mt-3" data-mapa-zona data-latitud="{{ $zona->latitud }}" data-longitud="{{ $zona->longitud }}" data-radio="{{ $zona->radio_km }}" aria-label="Mapa del centro y radio de vigilancia" tabindex="-1"></div>
                    <p class="zona-leyenda"><span><i aria-hidden="true"></i> Zona vigilada</span><span data-leyenda-foco @if(empty($resumenVigilancia['foco'])) hidden @endif><i aria-hidden="true"></i> Foco más cercano</span></p>
                    <details class="seguimiento-periodo mt-3" @if($evaluacion === null || $errors->has('fecha')) open @endif>
                        <summary>{{ $evaluacion === null ? 'Consultar un período' : 'Evaluación guardada y consulta por fechas' }}</summary>
                    @if($evaluacion !== null)
                        <div class="seguimiento-evaluacion" data-prioridad="{{ $evaluacion['prioridad'] }}">
                            <h3>{{ ($evaluacion['detecciones'] ?? 0) > 0 ? 'Aviso de focos dentro del radio' : $prioridades[$evaluacion['prioridad']] }}</h3>
                            @if($evaluacion['detecciones'] !== null)
                                <dl>
                                    <div><dt>Detecciones cercanas</dt><dd>{{ $evaluacion['detecciones'] }}</dd></div>
                                    <div><dt>Confianza alta</dt><dd>{{ $evaluacion['confianzaAlta'] }}</dd></div>
                                    @if(isset($evaluacion['puntosAlrededor']))
                                        <div><dt>Puntos fuera del centro</dt><dd>{{ $evaluacion['puntosAlrededor'] }}</dd></div>
                                        @if(isset($evaluacion['distanciaAlrededorKm']))
                                            <div><dt>Punto fuera del centro más cercano</dt><dd>{{ number_format($evaluacion['distanciaAlrededorKm'], 2, ',', '.') }} km</dd></div>
                                        @endif
                                    @elseif($evaluacion['distanciaKm'] !== null)
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
                                @if($evaluacion['prioridad'] === 'prioritaria')
                                    <p>Prioriza revisar el área de la detección más cercana. Comparte la ubicación con tu equipo antes de salir a campo.</p>
                                @else
                                    <p>Revisa si el foco está cerca de un camino, una parcela o una comunidad para decidir si amerita una visita.</p>
                                @endif
                                <p class="seguimiento-nota mt-2 mb-0">Una detección térmica requiere confirmación en campo.</p>
                            @endif
                        </div>
                        @if(in_array($evaluacion['prioridad'], ['prioritaria', 'seguimiento'], true))
                            <a class="btn btn-primary mt-3" href="#tareas-campo">Preparar verificación</a>
                        @endif
                    @endif
                    <form class="seguimiento-consulta" data-evaluar-zona method="post" action="{{ route('seguimiento.evaluar', $zona->id) }}">
                        @csrf
                        <label>Fecha final (opcional)
                            <input name="fecha" class="form-control" type="date" max="{{ $hoy }}" value="{{ old('fecha', $fechaInicial) }}">
                        </label>
                        <button class="btn btn-primary" type="submit">Evaluar últimos 7 días</button>
                    </form>
                    <p class="seguimiento-nota">Deja la fecha vacía para consultar hasta ahora. Las fechas se muestran en hora de Bolivia.</p>
                    </details>
                </section>
                <section class="seguimiento-tarjeta" id="tareas-campo">
                    <h2>Tareas de campo</h2>
                    @if($evaluacion !== null)
                        <form class="seguimiento-consulta" method="post" action="{{ route('seguimiento.tareas', $zona->id) }}">
                            @csrf
                            <input type="hidden" name="version_evaluacion" value="{{ hash('sha256', $zona->evaluacion) }}">
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
                                <p><strong class="seguimiento-estado">{{ $resultados[$tarea->resultado_tipo] }}</strong> · {{ $fechaBolivia($tarea->completada_en) }}</p>
                                <p class="seguimiento-resultado">{{ $tarea->resultado_nota }}</p>
                            @else
                                <details @if(old('tarea_formulario') == $tarea->id) open @endif>
                                    <summary>Pendiente · Registrar resultado</summary>
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

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script src="{{ asset('js/mapas/centrar-seleccion.js') }}?v=2" defer></script>
<script type="module" src="{{ asset('js/mapas/seguimiento.js') }}?v=vigilancia-5"></script>
@endpush
