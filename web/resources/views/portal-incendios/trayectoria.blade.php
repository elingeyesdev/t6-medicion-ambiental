@php
    $estado = $aviso->estado();
    $resultado = \App\Models\AvisoIncendio::RESULTADOS[$aviso->resultado] ?? null;
    $hitos = [
        ['Se detectó una señal de calor', $aviso->observado_en, 'Ubicación aproximada de la detección.', 'deteccion'],
        ['Se publicó el aviso', $aviso->publicado_en, 'La ficha quedó disponible para consulta pública.', 'publicacion'],
    ];
    if ($aviso->tomado_en) {
        $hitos[] = ['Una brigada tomó el aviso', $aviso->tomado_en, 'La ubicación quedó a cargo de una brigada para verificarla.', 'brigada'];
    }
    if ($aviso->resuelto_en) {
        $hitos[] = [$resultado ?? 'Resultado registrado', $aviso->resuelto_en, 'La brigada registró lo que pudo comprobar.', 'cierre'];
    } elseif ($estado === 'Vencido sin atención') {
        $hitos[] = ['Venció sin verificación', $aviso->vence_en, 'El aviso pasó al historial tras 72 horas sin asignación.', 'vencimiento'];
    }
@endphp
<section class="trayectoria-aviso" aria-labelledby="trayectoria-titulo">
    <header>
        <h3 id="trayectoria-titulo">Seguimiento del aviso</h3>
        <p>Hora de Bolivia</p>
    </header>
    <ol>
        @foreach ($hitos as [$titulo, $fecha, $detalle, $tipo])
            <li data-hito="{{ $tipo }}">
                <div class="trayectoria-hito">
                    <strong>{{ $titulo }}</strong>
                    <time datetime="{{ $fecha->toIso8601String() }}">{{ $fecha->setTimezone('America/La_Paz')->format('d/m/Y · H:i') }}</time>
                </div>
                <p>{{ $detalle }}</p>
            </li>
        @endforeach
    </ol>
    @if ($estado === 'Por verificar')
        <p class="trayectoria-proximo">Pendiente de asignación hasta el {{ $aviso->vence_en->setTimezone('America/La_Paz')->format('d/m/Y · H:i') }}.</p>
    @elseif ($estado === 'En verificación')
        <p class="trayectoria-proximo">A la espera del resultado de la brigada.</p>
    @endif
</section>
