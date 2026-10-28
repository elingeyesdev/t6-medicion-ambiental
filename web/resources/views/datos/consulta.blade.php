<div class="datos-titulo">
    <h2>{{ $punto->nombre }}</h2>
    <span class="datos-etiqueta">{{ $punto->tipo === 'referencia' ? 'Punto de referencia' : ($punto->tipo === 'iot' ? 'Sensor IoT' : 'Punto manual') }}</span>
    @if(! $punto->activo)<span class="datos-etiqueta simulado">Archivado</span>@endif
</div>
@if(collect($resultado['datos'])->contains('origen', 'simulado'))
    <p class="datos-aviso simulado">Esta consulta contiene datos simulados.</p>
@endif
@if(collect($resultado['datos'])->contains('origen', 'estimado'))
    <p class="datos-aviso estimado">Las estimaciones de modelo no son mediciones de una estación.</p>
@endif
<div class="datos-columnas">
    <section class="card datos-tarjeta">
        <div class="card-header"><h2>Ubicación</h2></div>
        <div id="mapa-datos" role="region" aria-label="Mapa de puntos ambientales" tabindex="0"></div>
        <p id="aviso-mapa-datos" class="datos-ayuda" role="status">Pulsa un punto para consultar sus registros.</p>
    </section>
    <section class="card datos-tarjeta">
        <div class="card-header"><h2>{{ $metricas[$metrica]['nombre'] }} · {{ $metricas[$metrica]['unidad'] }}</h2></div>
        <div class="card-body">
            <p>Promedios por hora</p>
            <div id="grafico-datos" role="img" aria-label="Evolución de {{ $metricas[$metrica]['nombre'] }}. Los valores se pueden consultar en la tabla de registros."></div>
            <div id="leyenda-datos" class="datos-leyenda"></div>
            @if($resultado['resumen'])
                <div class="datos-resumen">
                    @foreach($resultado['resumen'] as $resumen)
                        <div>
                            <span class="datos-etiqueta {{ $resumen['origen'] }}">{{ $origenes[$resumen['origen']] }}</span>
                            <p>@include('datos.fuente', ['fuente' => $resumen['fuente']])</p>
                            <strong>{{ number_format($resumen['minimo'], 1, ',', '.') }} – {{ number_format($resumen['maximo'], 1, ',', '.') }} {{ $metricas[$metrica]['unidad'] }}</strong>
                            <p>{{ $resumen['cantidad'] }} {{ $resumen['cantidad'] === 1 ? 'registro' : 'registros' }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="datos-vacio">No hay registros para este punto y período. Prueba otra selección.</p>
            @endif
        </div>
    </section>
</div>
<section class="card datos-tarjeta mt-4">
    <div class="card-header datos-titulo"><h2>Registros</h2><span>{{ $resultado['total'] }} en la consulta</span></div>
    <div class="table-responsive">
        <table class="table datos-tabla">
            <thead><tr><th>Fecha y hora · Bolivia</th><th>Valor</th><th>Origen</th><th>Fuente</th></tr></thead>
            <tbody>
                @forelse($resultado['datos'] as $lectura)
                    <tr>
                        <td>{{ \Carbon\CarbonImmutable::parse($lectura['instante'])->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                        <td><strong>{{ number_format($lectura['valor'], 1, ',', '.') }} {{ $lectura['unidad'] }}</strong></td>
                        <td><span class="datos-etiqueta {{ $lectura['origen'] }}">{{ $origenes[$lectura['origen']] }}</span></td>
                        <td>@include('datos.fuente', ['fuente' => $lectura['fuente']])</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay lecturas en esta selección.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($resultado['ultimaPagina'] > 1)
        <nav class="datos-paginacion" aria-label="Páginas de registros">
            @if($resultado['pagina'] > 1)
                <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['pagina' => $resultado['pagina'] - 1]) }}">Anterior</a>
            @endif
            <span>Página {{ $resultado['pagina'] }} de {{ $resultado['ultimaPagina'] }}</span>
            @if($resultado['pagina'] < $resultado['ultimaPagina'])
                <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['pagina' => $resultado['pagina'] + 1]) }}">Siguiente</a>
            @endif
        </nav>
    @endif
</section>
@if($metrica === 'ica')
    <p class="datos-ayuda">Un índice calculado no sustituye un análisis de laboratorio del agua.</p>
@endif
