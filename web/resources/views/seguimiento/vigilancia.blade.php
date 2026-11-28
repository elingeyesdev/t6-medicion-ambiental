@php
    $detecciones = $resumenVigilancia['detecciones'] ?? null;
    $tituloVigilancia = match (true) {
        $resumenVigilancia === null => 'Aún sin consulta',
        $detecciones === null => 'Sin cobertura para esta consulta',
        $detecciones === 0 => 'Sin focos en los datos consultados',
        $detecciones === 1 => 'Un foco de calor dentro del radio',
        default => $detecciones.' focos de calor dentro del radio',
    };
    $estadoVigilancia = match (true) {
        $resumenVigilancia === null || $detecciones === null => 'indeterminado',
        $detecciones > 0 => 'deteccion',
        default => 'sin-detecciones',
    };
    $detalleVigilancia = ($detecciones ?? 0) > 0
        ? 'La ubicación es aproximada y requiere verificación en campo.'
        : 'La cobertura satelital depende del período y de los datos disponibles.';
@endphp
<section class="zona-vigilancia" data-seguimiento-en-vivo data-espera="{{ $esperaAviso }}"
         data-estado="{{ $estadoVigilancia }}" data-resumen="{{ json_encode($resumenVigilancia) }}"
         aria-labelledby="titulo-vigilancia">
    <div class="zona-vigilancia-texto" role="status">
        <h3 id="titulo-vigilancia" data-titulo-zona>{{ $tituloVigilancia }}</h3>
        <p data-info-zona>{{ $detalleVigilancia }}</p>
    </div>
    <dl class="zona-datos">
        <div data-dato-distancia @if(!isset($resumenVigilancia['distancia'])) hidden @endif>
            <dt>Desde el centro de la zona</dt><dd>{{ isset($resumenVigilancia['distancia']) ? $resumenVigilancia['distancia'].' km' : '' }}</dd>
        </div>
        <div data-dato-deteccion @if(empty($resumenVigilancia['foco']['hora'])) hidden @endif>
            <dt>Hora de la detección más cercana</dt><dd>{{ $resumenVigilancia['foco']['hora'] ?? '' }}</dd>
        </div>
        <div data-dato-consulta @if($resumenVigilancia === null) hidden @endif>
            <dt>{{ isset($resumenVigilancia['consultadoEn']) ? 'Última consulta' : 'Período consultado hasta' }}</dt>
            <dd>{{ $resumenVigilancia['consultadoEn'] ?? $resumenVigilancia['hasta'] ?? '' }}</dd>
        </div>
    </dl>
    <p class="zona-origen" data-origen-zona @if($resumenVigilancia === null) hidden @endif>
        @if($resumenVigilancia) {{ $resumenVigilancia['fuente'] }} · {{ $resumenVigilancia['producto'] }} · Hora de Bolivia{{ $resumenVigilancia['cobertura'] === 'parcial' ? ' · Cobertura parcial' : '' }} @endif
    </p>
    <div class="zona-vigilancia-acciones">
        <a href="#mapa-zona" class="btn btn-outline-secondary" data-foco-en-mapa hidden>Ubicar foco en el mapa</a>
        <p class="zona-error" data-estado-zona role="status" hidden></p>
    </div>
</section>
