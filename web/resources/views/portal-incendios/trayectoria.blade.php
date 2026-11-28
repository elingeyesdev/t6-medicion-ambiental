@php
    $esEjercicio = str_starts_with($aviso->producto, 'SIMULACION');
    $resultado = \App\Models\AvisoIncendio::RESULTADOS[$aviso->resultado] ?? 'Resultado registrado';
    $hitos = [
        ['Primera señal de calor', $aviso->observado_en, 'deteccion'],
        ['Una brigada se hace cargo', $aviso->tomado_en, 'brigada'],
        [$aviso->estado() === 'Vencido sin atención' ? 'El aviso se cerró sin revisión' : 'Lo que encontró la brigada',
            $aviso->resuelto_en ?? ($aviso->estado() === 'Vencido sin atención' ? $aviso->vence_en : null),
            $aviso->estado() === 'Vencido sin atención' ? 'vencimiento' : 'cierre'],
    ];
    $seleccionado = $hitos[2][1] ? 'cierre' : ($aviso->tomado_en ? 'brigada' : 'deteccion');
    if ($hitos[2][2] === 'vencimiento') {
        $seleccionado = 'vencimiento';
    }
@endphp
<section class="trayectoria-aviso" aria-labelledby="trayectoria-titulo">
    <header>
        <h3 id="trayectoria-titulo">Qué pasó con este aviso</h3>
        <p>Hora de Bolivia</p>
    </header>
    <ol>
        @foreach ($hitos as [$titulo, $fecha, $tipo])
            <li data-hito="{{ $tipo }}" @unless($fecha) class="hito-pendiente" @endunless>
                <details @if($seleccionado === $tipo) open @endif>
                    <summary class="trayectoria-hito">
                        <strong>{{ $titulo }}</strong>
                        @if($fecha)
                            <time datetime="{{ $fecha->toIso8601String() }}">{{ $fecha->setTimezone('America/La_Paz')->format('d/m/Y · H:i') }}</time>
                        @else
                            <small>{{ $tipo === 'brigada' ? ($seleccionado === 'vencimiento' ? 'No asignado' : 'Sin asignar') : 'Pendiente' }}</small>
                        @endif
                    </summary>
                    <div class="hito-detalle">
                        @if($tipo === 'deteccion')
                            <p>{{ $esEjercicio ? 'Esta señal forma parte de un simulacro.' : 'El satélite registró una señal de calor en la zona.' }}</p>
                        @elseif($tipo === 'brigada' && $fecha)
                            <p>Una brigada se hizo cargo {{ $aviso->observado_en->lte($fecha) ? $fecha->locale('es')->diffForHumans($aviso->observado_en, \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 2).' después de la primera señal.' : 'del aviso.' }}</p>
                        @elseif($tipo === 'vencimiento')
                            <p>Ninguna brigada se hizo cargo durante las primeras 72 horas. Puedes encontrar el aviso en el historial.</p>
                        @elseif($tipo === 'cierre' && $fecha)
                            <p><strong>{{ $resultado }}</strong></p>
                            @if($aviso->comentario_publico && filled($aviso->comentario_verificacion))
                                <p class="hito-nota">{{ $aviso->comentario_verificacion }}</p>
                            @endif
                        @elseif($tipo === 'brigada')
                            @if($seleccionado === 'vencimiento')
                                <p>Ninguna brigada llegó a tomar este aviso.</p>
                            @else
                                <p>Una brigada puede tomar el aviso hasta el {{ $aviso->vence_en->setTimezone('America/La_Paz')->format('d/m/Y · H:i') }}.</p>
                            @endif
                        @else
                            <p>Cuando la brigada termine de revisar la zona, podrás leer aquí lo que encontró.</p>
                        @endif
                        @if($fecha && $tipo !== 'vencimiento')
                            <button type="button" class="hito-mapa" data-enfocar-hito data-etiqueta="{{ $tipo === 'cierre' ? $resultado : $titulo }}" hidden>Ver punto en el mapa</button>
                        @endif
                    </div>
                </details>
            </li>
        @endforeach
    </ol>
</section>
