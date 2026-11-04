@if($punto)
    @if($punto->activo)
        <details class="card datos-operacion mt-4">
            <summary>Registrar una lectura</summary>
            <form class="card-body datos-formulario" action="{{ route('sensores.registrar', $punto->codigo) }}" method="post">
                @csrf
                <div>
                    <label class="form-label" for="lectura-variable">Variable</label>
                    <select id="lectura-variable" name="metrica" class="form-select">
                        @foreach($metricas as $clave => $item)
                            <option value="{{ $clave }}">{{ $item['nombre'] }} · {{ $item['unidad'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="lectura-valor">Valor</label>
                    <input id="lectura-valor" name="valor" class="form-control" type="number" step="any" required value="{{ old('valor') }}">
                </div>
                <div>
                    <label class="form-label" for="lectura-fecha">Fecha y hora · Bolivia</label>
                    <input id="lectura-fecha" name="instante" class="form-control" type="datetime-local" required max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}" value="{{ old('instante', now('America/La_Paz')->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label class="form-label" for="lectura-origen">Origen</label>
                    <select id="lectura-origen" name="origen" class="form-select">
                        @foreach($origenes as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Guardar lectura</button>
            </form>
        </details>
    @endif
    <details class="card datos-operacion mt-3">
        <summary>Importar lecturas</summary>
        <div class="card-body">
            <p>Hasta 1000 lecturas por archivo. Las filas repetidas se conservan una sola vez.</p>
            <p>Punto seleccionado: <strong>{{ $punto->codigo }}</strong></p>
            <p><a href="{{ asset('ejemplos/lecturas.csv') }}" download>Descargar plantilla</a></p>
            <form action="{{ route('sensores.importar') }}" method="post" enctype="multipart/form-data" class="datos-formulario">
                @csrf
                <div>
                    <label for="formato-datos" class="form-label">Formato</label>
                    <select id="formato-datos" name="formato" class="form-select">
                        <option value="csv" @selected(old('formato', 'csv') === 'csv')>Lecturas CSV</option>
                        <option value="historial" @selected(old('formato') === 'historial')>Historial saneado JSON</option>
                    </select>
                </div>
                <div>
                    <label for="archivo-datos" class="form-label">Archivo de lecturas</label>
                    <input id="archivo-datos" name="archivo" class="form-control" type="file" accept=".csv,.txt,.json" required>
                </div>
                <label class="form-check">
                    <input type="checkbox" name="saneado" value="1" class="form-check-input">
                    <span class="form-check-label">Para el historial JSON: revisé la procedencia y retiré los datos privados.</span>
                </label>
                <button class="btn btn-primary" type="submit">Importar</button>
            </form>
            <details class="mt-3">
                <summary>Preparar un historial</summary>
                <p class="mt-2">Usa códigos de puntos existentes, fecha con zona horaria, unidad y fuente en cada lectura. Conserva el origen registrado, estimado o simulado.</p>
                <pre class="p-3 bg-light text-wrap text-break"><code>{"formato":"geosur.historial.v1", "lecturas":[
  {"tiempo":"2026-10-27T12:00:00Z", "localidad":"{{ $punto->codigo }}",
   "metrica":"temperatura", "valor":25.5, "unidad":"°C",
   "origen":"simulado", "fuente":"ejemplo-local"}
]}</code></pre>
                <p>El ejemplo es simulado. No incluyas cuentas, claves ni archivos de una base de datos.</p>
            </details>
        </div>
    </details>
    <form class="mt-3" action="{{ route('sensores.estado', $punto->codigo) }}" method="post">
        @csrf
        @method('PATCH')
        <input type="hidden" name="activo" value="{{ $punto->activo ? 0 : 1 }}">
        <button type="submit" class="btn btn-outline-primary">{{ $punto->activo ? 'Archivar punto' : 'Reactivar punto' }}</button>
    </form>
@endif
<details class="card datos-operacion mt-4">
    <summary>Agregar punto de monitoreo</summary>
    <form class="card-body datos-formulario" method="post" action="{{ route('sensores.guardar') }}">
        @csrf
        <div>
            <label for="nombre-punto" class="form-label">Nombre</label>
            <input id="nombre-punto" name="nombre" class="form-control" maxlength="100" required value="{{ old('nombre') }}">
        </div>
        <div>
            <label for="pais-punto" class="form-label">País o territorio</label>
            <select id="pais-punto" name="pais" class="form-select">
                @foreach($paises as $pais)
                    <option value="{{ $pais['codigo'] }}" @selected(old('pais', 'BO') === $pais['codigo'])>{{ $pais['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="latitud-punto" class="form-label">Latitud</label>
            <input id="latitud-punto" name="latitud" class="form-control" type="number" step="any" min="-90" max="90" required value="{{ old('latitud') }}">
        </div>
        <div>
            <label for="longitud-punto" class="form-label">Longitud</label>
            <input id="longitud-punto" name="longitud" class="form-control" type="number" step="any" min="-180" max="180" required value="{{ old('longitud') }}">
        </div>
        <p>Puedes elegir las coordenadas pulsando el fondo del mapa.</p>
        <button class="btn btn-primary" type="submit">Guardar punto</button>
    </form>
</details>
