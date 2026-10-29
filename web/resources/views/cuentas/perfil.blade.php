@extends('layouts.app')
@section('titulo', 'Mi cuenta')
@section('clase-cuerpo', 'pagina-cuentas')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/cuentas.css') }}">
@endpush
@section('contenido')
<div class="cuentas-contenido">
    <h1>Mi cuenta</h1>
    @include('cuentas.estado')
    <div class="cuentas-columnas">
        <section class="card cuenta-tarjeta" id="unidades">
            <div class="card-header"><h2 class="card-title">Unidades de medida</h2></div>
            <div class="card-body">@include('cuentas.unidades')</div>
        </section>
        <section class="card cuenta-tarjeta">
            <div class="card-header"><h2 class="card-title">Tus datos</h2><span class="cuenta-rol">{{ $usuario->rol->nombre() }}</span></div>
            <div class="card-body">
                <p class="cuenta-correo">{{ $usuario->email }}</p>
                <form method="post" action="{{ route('cuenta.nombre') }}">
                    @csrf @method('PUT')
                    <label for="nombre" class="form-label">Nombre</label>
                    <input id="nombre" name="name" class="form-control mb-4" value="{{ old('name', $usuario->name) }}" maxlength="100" autocomplete="name" required>
                    <button class="btn btn-primary" type="submit">Guardar nombre</button>
                </form>
            </div>
        </section>
        <section class="card cuenta-tarjeta">
            <div class="card-header"><h2 class="card-title">Tu ubicación de referencia</h2></div>
            <div class="card-body">
                <p id="ayuda-ubicacion">Marca un lugar en el mapa o muévelo y pulsa Enter.</p>
                <div id="cuenta-mapa" tabindex="0" role="region" aria-label="Elegir ubicación" aria-describedby="ayuda-ubicacion"></div>
                <p id="estado-ubicacion" class="mt-3" role="status">{{ $preferencias['latitud'] !== null ? 'Tienes una ubicación guardada.' : 'No has guardado una ubicación.' }}</p>
                <form method="post" action="{{ route('cuenta.preferencias') }}">
                    @csrf @method('PUT')
                    <input type="hidden" id="latitud" name="latitud" value="{{ old('latitud', $preferencias['latitud']) }}">
                    <input type="hidden" id="longitud" name="longitud" value="{{ old('longitud', $preferencias['longitud']) }}">
                    <div class="cuenta-acciones"><button class="btn btn-primary" type="submit">Guardar ubicación</button><button id="quitar-ubicacion" class="btn btn-outline-primary" type="button">Quitar ubicación</button></div>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
@push('scripts')
@include('cuentas.configuracion-unidades')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="module" src="{{ asset('js/cuentas/ubicacion.js') }}"></script>
@endpush
