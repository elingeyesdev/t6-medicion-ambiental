<div class="rep-condicion">
    <label>Variable<select class="form-select" name="condiciones[{{ $indice }}][variable]">@foreach($metricas as $clave => $m)<option value="{{ $clave }}" @selected($condicion['variable'] === $clave)>{{ $m['nombre'] }} ({{ $m['unidad'] }})</option>@endforeach</select></label>
    <label>Comparación<select class="form-select" name="condiciones[{{ $indice }}][operador]">@foreach(['>' => 'Mayor que', '<' => 'Menor que', '=' => 'Igual a'] as $clave => $texto)<option value="{{ $clave }}" @selected($condicion['operador'] === $clave)>{{ $texto }}</option>@endforeach</select></label>
    <label>Valor<input class="form-control" type="number" step="any" required name="condiciones[{{ $indice }}][valor]" value="{{ $condicion['valor'] }}"></label>
    <button type="button" class="btn btn-outline-primary" data-quitar>Quitar condición</button>
</div>
