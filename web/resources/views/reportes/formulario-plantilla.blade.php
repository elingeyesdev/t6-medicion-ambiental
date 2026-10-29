<input type="hidden" name="grafico" value="0">
<label class="form-label" for="pl-{{ $prefijo }}">Nombre</label>
<input id="pl-{{ $prefijo }}" name="nombre" maxlength="100" required class="form-control" value="{{ $nombre }}">
<label class="form-label mt-3" for="tipo-{{ $prefijo }}">Tipo</label>
<select id="tipo-{{ $prefijo }}" name="tipo" class="form-select">
    @foreach($tipos as $clave => $texto)<option value="{{ $clave }}" @selected(($config['tipo'] ?? 'PERSONALIZADA') === $clave)>{{ $texto }}</option>@endforeach
</select>
<p class="mt-2">El tipo organiza tus plantillas. Tú eliges los filtros.</p>
<fieldset class="rep-columnas">
    <legend>Columnas del reporte</legend>
    @foreach($columnas as $c => $n)<label><input type="checkbox" class="form-check-input" name="columnas[]" value="{{ $c }}" @checked(in_array($c, $config['columnas'], true))> {{ $n }}</label>@endforeach
</fieldset>
<fieldset class="rep-filtros" data-filtros>
    <legend>Condiciones</legend>
    <p>Se deben cumplir todas en la misma zona, hora y fuente.</p>
    <div data-lista>
        @foreach($config['condiciones'] ?? [] as $i => $condicion)
            @include('reportes.condicion', ['indice' => $i, 'condicion' => $condicion])
        @endforeach
    </div>
    <template>@include('reportes.condicion', ['indice' => '__indice__', 'condicion' => ['variable' => 'temperatura', 'operador' => '>', 'valor' => '']])</template>
    <button type="button" class="btn btn-outline-primary mt-3" data-agregar>Añadir condición</button>
</fieldset>
<label class="d-block my-3"><input type="checkbox" name="grafico" value="1" class="form-check-input" @checked($config['grafico'])> Incluir gráfico en la pantalla del reporte</label>
