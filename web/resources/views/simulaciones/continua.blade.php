<section class="card mt-4" data-simulacion-continua data-estado="{{ url('/simulacion-continua/estado') }}" data-accion="{{ url('/simulacion-continua/accion') }}">
    <div class="card-header"><h2>Simulación continua global</h2><span class="badge bg-orange-lt">Simulado</span></div>
    <div class="card-body">
        <p>Prueba las cinco variables en todas las localidades del catálogo. Cada pantalla muestra el mismo recorrido.</p>
        <p data-estado-texto role="status" aria-live="polite">Consultando simulación…</p>
        @can('simulaciones.gestionar')
        <form data-preparar class="sim-formulario">@csrf
            <label>Código para repetir<input class="form-control" name="semilla" value="global-1" pattern="[a-zA-Z0-9_-]+" maxlength="40" required></label>
            <label>Actualizar cada<select class="form-select" name="intervalo_ms"><option value="3000">3 segundos</option><option value="10000">10 segundos</option><option value="60000">Un minuto</option></select></label>
            <div><button class="btn btn-outline-primary" type="submit">Preparar recorrido global</button></div>
        </form>
        <div class="sim-reproductor mt-3">@foreach(['iniciar'=>'Iniciar','pausar'=>'Pausar','detener'=>'Detener','reiniciar'=>'Reiniciar'] as $a=>$n)<button type="button" class="btn btn-outline-primary" data-control="{{ $a }}" disabled>{{ $n }}</button>@endforeach</div>
        <form data-inyectar class="sim-formulario mt-3" hidden>
            <label>Localidad<select class="form-select" name="punto" required></select></label>
            <label>Variable<select class="form-select" name="variable">@foreach(array_intersect_key(app(\App\Services\Datos\CatalogoAmbiental::class)->metricas(),array_flip(\App\Services\Simulaciones\MotorContinuo::METRICAS)) as $k=>$m)<option value="{{ $k }}">{{ $m['nombre'] }} ({{ $m['unidad'] }})</option>@endforeach</select></label>
            <label>Valor simulado<input class="form-control" name="valor" type="number" step="any" required></label>
            <div><button class="btn btn-outline-primary" type="submit">Aplicar valor simulado</button></div>
        </form>
        @endcan
        <p class="mt-3">Los cambios permanecen en esta simulación. Puedes guardar un corte desde Reportes.</p>
        <div class="table-responsive" style="max-height:24rem"><table class="table"><thead><tr><th>Localidad</th><th>Temperatura · °C</th><th>Humedad · %</th><th>Aire · AQI</th><th>Agua · ICA</th><th>Ruido · dB</th></tr></thead><tbody data-puntos></tbody></table></div>
    </div>
</section>
