<section id="nueva-simulacion" class="sim-escenarios" aria-labelledby="sim-configurar-titulo">
    <div class="sim-section-header"><div><p class="sim-eyebrow">Simulación por lotes · histórico / futuro</p><h2 id="sim-configurar-titulo">Configurar <em>simulación</em></h2></div><span class="sim-page-tag">Hasta 3 zonas</span></div>
    <form action="{{ route('simulaciones.guardar') }}" method="post" class="sim-configuracion">@csrf
        <fieldset class="fronteras-panel sim-zonas">
            <legend class="fronteras-header">Fronteras de Simulación</legend>
            <div class="fronteras-body">
                <p class="sim-ayuda"><strong>Zonas a comparar</strong> · Selecciona puntos de referencia o regiones para comparar sus escenarios.</p>
                @for($i=0;$i<3;$i++)
                    <div class="fp-zona-box">
                        <div class="fp-zona-header"><span class="sim-numero">0{{ $i+1 }}</span><h3>Zona {{ $i+1 }}</h3><span>{{ $i===0?'Principal':'Opcional' }}</span></div>
                        <label class="form-label" for="sim-zona-{{ $i }}">Punto o región</label>
                        <select id="sim-zona-{{ $i }}" name="zonas[{{ $i }}][punto]" class="form-select" @if($i===0) required @endif>
                            <option value="">Elegir una zona</option>
                            <optgroup label="Puntos de referencia">@foreach($puntos as $p)<option value="{{ $p['codigo'] }}" @selected(old('zonas.'.$i.'.punto',$i===0?'santacruz':'')===$p['codigo'])>{{ $p['nombre'] }} · {{ $p['pais'] }}</option>@endforeach</optgroup>
                            <optgroup label="Regiones">@foreach($regiones as $r)<option value="region:{{ $r['codigo'] }}" @selected(old('zonas.'.$i.'.punto')==='region:'.$r['codigo'])>{{ $r['nombre'] }} · {{ $r['pais'] }}</option>@endforeach</optgroup>
                        </select>
                    </div>
                @endfor
            </div>
            <div class="fronteras-footer"><p>Los parámetros de tiempo son comunes a todas las zonas para mantener la comparación sincronizada.</p></div>
        </fieldset>
        <div class="msim-box">
            <div class="msim-box-header"><h3>Configuración de escenarios</h3><span class="sim-page-tag">Simulado</span></div>
            <div class="sim-datos-generales">
                <div><label class="form-label" for="sim-nombre">Nombre</label><input id="sim-nombre" name="nombre" class="form-control" maxlength="100" required value="{{ old('nombre','Prueba ambiental') }}"></div>
                <div><label class="form-label" for="sim-metrica">Dato a simular</label><select id="sim-metrica" name="metrica" class="form-select">@foreach($metricas as $clave=>$m)<option value="{{ $clave }}" @selected(old('metrica','temperatura')===$clave)>{{ $m['nombre'] }} ({{ $m['unidad'] }})</option>@endforeach</select></div>
            </div>
            <div class="msim-zone-selector" aria-label="Configuración por zona" data-zona-tabs hidden>
                @for($i=0;$i<3;$i++)<button type="button" class="msim-zone-tab" id="sim-tab-{{ $i }}" data-zona-tab="{{ $i }}" aria-controls="sim-panel-{{ $i }}">Zona {{ $i+1 }}</button>@endfor
            </div>
            @for($i=0;$i<3;$i++)
                <section class="sim-zona msim-card" id="sim-panel-{{ $i }}" data-zona-panel="{{ $i }}" aria-labelledby="sim-zona-titulo-{{ $i }}">
                    <div class="msim-card-top"><span class="sim-numero">0{{ $i+1 }}</span><span class="sim-eyebrow">Dato a simular</span><span class="msim-badge">Escenario por zona</span></div>
                    <h3 class="msim-metric-title" id="sim-zona-titulo-{{ $i }}"><span data-metrica-nombre>{{ $metricas[old('metrica','temperatura')]['nombre'] ?? 'Variable ambiental' }}</span> <em data-zona-nombre="{{ $i }}">en Zona {{ $i+1 }}</em></h3>
                    <p class="msim-metric-desc">Selecciona el escenario y la intensidad para esta zona.</p>
                    <div class="sim-campo-escenario">
                        <label class="form-label" for="sim-escenario-{{ $i }}">Escenario</label>
                        <select id="sim-escenario-{{ $i }}" name="zonas[{{ $i }}][escenario]" class="form-select sim-escenario" data-elegido="{{ old('zonas.'.$i.'.escenario','calor') }}">
                            @foreach($escenarios[old('metrica','temperatura')] ?? $escenarios['temperatura'] as $e)<option value="{{ $e['id'] }}" @selected(old('zonas.'.$i.'.escenario','calor')===$e['id'])>{{ $e['nombre'] }}</option>@endforeach
                            <option value="personalizado" @selected(old('zonas.'.$i.'.escenario')==='personalizado')>Personalizado</option>
                        </select>
                    </div>
                    <div class="msim-escenarios-grid" data-escenario-tarjetas hidden></div>
                    <div class="sim-campo-nivel"><label class="form-label" for="sim-nivel-{{ $i }}">Intensidad del escenario</label><select id="sim-nivel-{{ $i }}" name="zonas[{{ $i }}][nivel]" class="form-select">@foreach(['bajo'=>'Baja','medio'=>'Media','alto'=>'Alta'] as $v=>$n)<option value="{{ $v }}" @selected(old('zonas.'.$i.'.nivel','medio')===$v)>{{ $n }}</option>@endforeach</select></div>
                    <div class="msim-intensidad-selector" data-nivel-botones hidden>
                        @foreach(['bajo'=>'Baja','medio'=>'Media','alto'=>'Alta'] as $v=>$n)<button class="msim-nivel" type="button" data-nivel="{{ $v }}" aria-pressed="false"><span class="msim-nivel-dot msim-nivel-dot--{{ $v }}" aria-hidden="true"></span>{{ $n }}</button>@endforeach
                    </div>
                    <details class="sim-ajustes" @if(old('zonas.'.$i.'.escenario')==='personalizado') open @endif>
                        <summary>Ajustar valores</summary>
                        <p class="sim-ayuda">Opcional para los casos del catálogo; obligatorio si eliges Personalizado. Usa la unidad base de la variable.</p>
                        <div class="sim-parametros">
                            <div><label class="form-label" for="sim-inicial-{{ $i }}">Valor inicial</label><input id="sim-inicial-{{ $i }}" name="zonas[{{ $i }}][inicio]" type="number" step="any" class="form-control" value="{{ old('zonas.'.$i.'.inicio') }}"></div>
                            <div><label class="form-label" for="sim-final-{{ $i }}">Valor objetivo</label><input id="sim-final-{{ $i }}" name="zonas[{{ $i }}][fin]" type="number" step="any" class="form-control" value="{{ old('zonas.'.$i.'.fin') }}"></div>
                            <div><label class="form-label" for="sim-curva-{{ $i }}">Recorrido</label><select id="sim-curva-{{ $i }}" name="zonas[{{ $i }}][curva]" class="form-select"><option value="">Del escenario</option>@foreach(['lineal'=>'Gradual','pico'=>'Subida y bajada','exponencial'=>'Aumento acelerado'] as $v=>$n)<option value="{{ $v }}" @selected(old('zonas.'.$i.'.curva')===$v)>{{ $n }}</option>@endforeach</select></div>
                        </div>
                    </details>
                </section>
            @endfor
            <div class="msim-controls-row">
                <div><label class="form-label" for="sim-inicio">Inicio · hora de Bolivia</label><input id="sim-inicio" type="datetime-local" name="inicio" class="form-control" required value="{{ old('inicio',now('America/La_Paz')->subDay()->format('Y-m-d\TH:i')) }}"></div>
                <div><label class="form-label" for="sim-dias">Cantidad de días</label><input id="sim-dias" type="number" min="1" max="30" name="dias" class="form-control" required value="{{ old('dias',1) }}"></div>
                <div><label class="form-label" for="sim-intervalo">Int. guardado</label><select id="sim-intervalo" name="intervalo" class="form-select">@foreach([15=>'15 minutos',30=>'30 minutos',60=>'Una hora',180=>'Tres horas',360=>'Seis horas',1440=>'Un día'] as $min=>$etiqueta)<option value="{{ $min }}" @selected((int)old('intervalo',60)===$min)>{{ $etiqueta }}</option>@endforeach</select></div>
                <div class="sim-semilla"><label class="form-label" for="sim-semilla">Código para repetir la prueba</label><input id="sim-semilla" name="semilla" class="form-control" maxlength="40" pattern="[a-zA-Z0-9_-]+" required value="{{ old('semilla','prueba-1') }}"></div>
            </div>
            <div class="msim-footer sim-enviar"><p>Las fechas futuras pertenecen al escenario. No se guardan como mediciones reales.</p><button class="sim-btn sim-btn--start" type="submit">Preparar simulación @include('simulaciones.icono', ['icono'=>'iniciar'])</button></div>
        </div>
    </form>
</section>
