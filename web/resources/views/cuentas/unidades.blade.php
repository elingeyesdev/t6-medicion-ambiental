@php($unidadesElegidas = app(\App\Services\Datos\UnidadesPresentacion::class)->preferencias(auth()->user()))
<form method="post" action="{{ route('cuenta.unidades') }}" data-preferencias-unidades>
    @csrf @method('PUT')
    @foreach(config('unidades') as $clave => $grupo)
        <label class="form-label" for="unidad-{{ $clave }}">{{ $grupo['nombre'] }}</label>
        <select class="form-select mb-3" id="unidad-{{ $clave }}" name="unidades[{{ $clave }}]">
            @foreach($grupo['opciones'] as $codigo => $opcion)<option value="{{ $codigo }}" @selected($unidadesElegidas[$clave] === $codigo)>{{ $opcion['nombre'] }}</option>@endforeach
        </select>
    @endforeach
    <button type="submit" class="btn btn-primary">Guardar unidades</button>
    <p class="mt-3 mb-0" role="status" data-estado-unidades></p>
</form>
