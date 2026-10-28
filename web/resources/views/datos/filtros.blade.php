<form class="card datos-filtros" method="get" action="{{ route('sensores') }}">
    <div>
        <label class="form-label" for="pais-datos">País o territorio</label>
        <select id="pais-datos" name="pais" class="form-select">
            <option value="">Todos</option>
            @foreach($paises as $pais)
                <option value="{{ $pais['codigo'] }}" @selected(request('pais') === $pais['codigo'])>{{ $pais['nombre'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="punto-datos">Punto</label>
        <select id="punto-datos" name="punto" class="form-select">
            @if($punto && ! $punto->activo)
                <option value="{{ $punto->codigo }}" selected>{{ $punto->nombre }} · Archivado</option>
            @endif
            @foreach($puntos as $opcion)
                <option value="{{ $opcion->codigo }}" @selected($punto?->id === $opcion->id)>{{ $opcion->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="metrica-datos">Variable</label>
        <select id="metrica-datos" name="metrica" class="form-select">
            @foreach($metricas as $clave => $item)
                <option value="{{ $clave }}" @selected($metrica === $clave)>{{ $item['nombre'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="origen-datos">Origen</label>
        <select id="origen-datos" name="origen" class="form-select">
            <option value="">Todos</option>
            @foreach($origenes as $clave => $nombre)
                <option value="{{ $clave }}" @selected(request('origen') === $clave)>{{ $nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="datos-fecha">
        <label class="form-label" for="desde-datos">Desde</label>
        <input id="desde-datos" class="form-control" type="datetime-local" name="desde" value="{{ $desde }}" max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}" required>
    </div>
    <div class="datos-fecha">
        <label class="form-label" for="hasta-datos">Hasta</label>
        <input id="hasta-datos" class="form-control" type="datetime-local" name="hasta" value="{{ $hasta }}" max="{{ now('America/La_Paz')->format('Y-m-d\TH:i') }}" required>
    </div>
    <div class="datos-consultar">
        <button class="btn btn-primary" type="submit">Ver registros</button>
        <span>Hora de Bolivia · Hasta 7 días.</span>
    </div>
</form>
