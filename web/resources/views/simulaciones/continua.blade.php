@if($compacto ?? false)
<details class="monitoreo-unidades" data-simulacion-continua data-modo="compacto" data-estado="{{ url('/simulacion-continua/estado') }}">
    <summary>Simulación continua <span class="badge bg-orange-lt">Simulado</span></summary>
    <div class="p-3">
        <label class="form-check"><input type="checkbox" class="form-check-input" data-mostrar-continua><span class="form-check-label">Mostrar puntos de simulación continua</span></label>
        <label class="form-label mt-3" for="continua-variable">Variable simulada</label>
        <select id="continua-variable" class="form-select" data-variable-continua>@foreach(array_intersect_key($metricas,array_flip(\App\Services\Simulaciones\MotorContinuo::METRICAS)) as $k=>$m)<option value="{{ $k }}">{{ $m['nombre'] }} ({{ $m['unidad'] }})</option>@endforeach</select>
        <p class="mt-3" data-estado-texto role="status" aria-live="polite">Activa la capa para consultar la simulación.</p>
        <p data-resumen-continua role="status" aria-live="polite"></p>
        <p>Usa el país y la región elegidos. Consulta el mapa al cambiar de región. Los puntos naranjas «S» son simulados; su instante es independiente del historial.</p>
        @can('simulaciones.gestionar')<a href="{{ route('simulaciones') }}">Abrir controles de simulación</a>@endcan
    </div>
</details>
@else
<section class="sim-consola" id="sim-tiempo-real" aria-labelledby="sim-continua-titulo" data-simulacion-continua data-estado="{{ url('/simulacion-continua/estado') }}" data-accion="{{ url('/simulacion-continua/accion') }}">
    <div class="sim-controls-card">
        <div class="sim-controls-top">
            <div class="sim-status">
                <span class="sim-status-dot">@include('simulaciones.icono', ['icono'=>'detener'])</span>
                <div><h2 class="sim-eyebrow" id="sim-continua-titulo">Simulación continua global</h2><p class="sim-status-text" data-estado-texto role="status" aria-live="polite">Consultando simulación…</p></div>
            </div>
            <div class="sim-stats">
                <div class="sim-stat"><span class="sim-stat-label">Pasos</span><strong class="sim-stat-value" data-continua-paso>—</strong></div>
                <div class="sim-stat"><span class="sim-stat-label">Última actualización</span><strong class="sim-stat-value" data-continua-instante>—</strong></div>
                <div class="sim-stat"><span class="sim-stat-label">Puntos activos</span><strong class="sim-stat-value" data-continua-puntos>—</strong></div>
            </div>
        </div>
        @can('simulaciones.gestionar')
        <div class="sim-controls-actions sim-reproductor">
            @foreach(['iniciar'=>'Iniciar tiempo real','pausar'=>'Pausar','detener'=>'Detener','reiniciar'=>'Reiniciar'] as $a=>$n)
                <button type="button" class="sim-btn {{ $a==='iniciar'?'sim-btn--start':($a==='detener'?'sim-btn--stop':'sim-btn--secondary') }}" data-control="{{ $a }}" disabled>@include('simulaciones.icono', ['icono'=>$a]){{ $n }}</button>
            @endforeach
            <span class="sim-tick" data-continua-intervalo>Recorrido sincronizado</span>
        </div>
        <form data-preparar class="sim-batch-card sim-preparar" method="post" action="{{ url('/simulacion-continua/accion') }}">@csrf
            <div class="sim-batch-header"><h3 class="sim-batch-title">Preparar recorrido global</h3><p class="sim-batch-hint">Cinco variables en todas las localidades del catálogo.</p></div>
            <div class="sim-batch-row">
                <div><label class="form-label" for="continua-semilla">Código para repetir</label><input id="continua-semilla" class="form-control" name="semilla" value="global-1" pattern="[a-zA-Z0-9_-]+" maxlength="40" required></div>
                <div><label class="form-label" for="continua-intervalo">Velocidad tick</label><select id="continua-intervalo" class="form-select" name="intervalo_ms"><option value="3000">3 segundos</option><option value="10000">10 segundos</option><option value="60000">Un minuto</option></select></div>
                <button class="sim-btn sim-btn--start" type="submit">@include('simulaciones.icono', ['icono'=>'simular'])Preparar recorrido global</button>
            </div>
        </form>
        @endcan
    </div>
    <section class="sim-units-card" aria-labelledby="sim-unidades-titulo">
        <div class="sim-units-header"><h3 class="sim-eyebrow" id="sim-unidades-titulo">Unidades de medida</h3><p>Los cambios aplican en todo el panel y el mapa.</p></div>
        @include('cuentas.unidades')
    </section>
    <div class="sim-summary-grid" aria-label="Promedios de la simulación continua">
        @foreach(['aqi'=>'Calidad del aire','ica'=>'Calidad del agua','ruido'=>'Nivel de ruido','temperatura'=>'Temperatura','humedad'=>'Humedad'] as $k=>$nombre)
            <article class="sim-summary-card" data-promedio="{{ $k }}" data-unidad="{{ $metricas[$k]['unidad'] }}">
                <div class="sim-summary-header">@include('simulaciones.icono', ['icono'=>$k])<h3>{{ $nombre }}</h3></div>
                <strong class="sim-summary-value">—</strong><span class="sim-summary-tag">Sin lecturas simuladas</span>
            </article>
        @endforeach
    </div>
    <section class="sim-table-card" aria-labelledby="sim-datos-titulo">
        <div class="sim-section-header"><h3 id="sim-datos-titulo">Datos <em>por localidad</em></h3><span class="sim-page-tag">Simulado</span></div>
        <div class="table-responsive sim-table-wrapper"><table class="sim-table"><caption class="visually-hidden">Valores de la simulación continua en sus unidades base</caption><thead><tr><th scope="col">Localidad</th><th scope="col">Temperatura · °C</th><th scope="col">Humedad · %</th><th scope="col">Aire · AQI</th><th scope="col">Agua · ICA</th><th scope="col">Ruido · dB</th></tr></thead><tbody data-puntos><tr><td colspan="6" class="sim-vacio">Prepara e inicia una simulación para ver sus valores.</td></tr></tbody></table></div>
        <p class="sim-footer-note">Tabla en unidades base · Los cambios permanecen en esta simulación. Puedes guardar un corte desde Reportes.</p>
    </section>
    @can('simulaciones.gestionar')
    <form data-inyectar class="inject-card" method="post" action="{{ url('/simulacion-continua/accion') }}" hidden>@csrf
        <div class="inject-card-header"><h3 class="inject-title">Inyección <em>manual</em> de datos</h3><p>Aplica un valor a una localidad del recorrido activo o pausado.</p></div>
        <div class="sim-formulario inject-form">
            <div><label class="form-label" for="continua-punto">Localidad</label><select id="continua-punto" class="form-select" name="punto" required></select></div>
            <div><label class="form-label" for="continua-variable-inyectada">Variable</label><select id="continua-variable-inyectada" class="form-select" name="variable">@foreach(array_intersect_key($metricas,array_flip(\App\Services\Simulaciones\MotorContinuo::METRICAS)) as $k=>$m)<option value="{{ $k }}">{{ $m['nombre'] }} ({{ $m['unidad'] }})</option>@endforeach</select></div>
            <div><label class="form-label" for="continua-valor">Valor simulado · unidad base</label><input id="continua-valor" class="form-control" name="valor" type="number" step="any" required></div>
            <div class="sim-enviar"><button class="sim-btn sim-btn--start" type="submit">Aplicar valor simulado</button><button class="sim-btn sim-btn--secondary" type="reset">Limpiar valor</button></div>
        </div>
    </form>
    @endcan
    <noscript><p class="sim-aviso">Activa JavaScript para consultar y controlar el recorrido continuo. Puedes preparar escenarios con el formulario inferior.</p></noscript>
</section>
@endif
