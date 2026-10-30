@extends('layouts.app')
@section('titulo', 'Mi cuenta')
@section('clase-cuerpo', 'pagina-cuentas')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/cuentas.css') }}">
@endpush
@section('contenido')
<div class="cuentas-contenido">
    <header class="cuenta-cabecera"><div><h1>Preferencias y <em>Localidad</em></h1><p>Ubica tu área de interés en el mapa y configura tu cuenta de GeoSur.</p></div><span class="cuenta-etiqueta">Mi cuenta</span></header>
    <nav class="cuenta-pestanas" aria-label="Preferencias y notificaciones"><a href="{{ route('cuenta') }}" aria-current="page">Localidad y cuenta</a><a href="{{ route('avisos') }}">Canales y avisos</a></nav>
    @include('cuentas.estado')
    <section class="card cuenta-tarjeta cuenta-ubicacion">
        <div class="card-header"><span class="cuenta-icono" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg></span><div><h2 class="card-title">Ubicación en el Mapa</h2><p id="estado-ubicacion" role="status">{{ $preferencias['latitud'] !== null ? 'Tienes una ubicación guardada.' : 'No has guardado una ubicación.' }}</p></div></div>
        <div class="card-body">
            <div><p id="ayuda-ubicacion">Marca un lugar en el mapa o muévelo y pulsa Enter. Los avisos se relacionan con tu ubicación y sus alrededores (50 km).</p><div id="cuenta-mapa" tabindex="0" role="region" aria-label="Elegir ubicación" aria-describedby="ayuda-ubicacion"></div></div>
            <div>
                <form method="post" action="{{ route('cuenta.preferencias') }}" class="cuenta-coordenadas">
                    @csrf @method('PUT')
                    <div><label for="latitud" class="form-label">Latitud</label><input class="form-control" type="text" id="latitud" name="latitud" readonly placeholder="Haz clic en el mapa" value="{{ old('latitud', $preferencias['latitud']) }}"></div>
                    <div><label for="longitud" class="form-label">Longitud</label><input class="form-control" type="text" id="longitud" name="longitud" readonly placeholder="Haz clic en el mapa" value="{{ old('longitud', $preferencias['longitud']) }}"></div>
                    <div class="cuenta-acciones"><button class="btn btn-primary" type="submit">Guardar ubicación</button><button id="quitar-ubicacion" class="btn btn-outline-primary" type="button">Quitar ubicación</button></div>
                </form>
                <div class="cuenta-preferencias-enlace"><a href="{{ route('avisos') }}">Configurar variables y canales de notificación</a></div>
            </div>
        </div>
    </section>
    <div class="cuentas-columnas">
        <section class="card cuenta-tarjeta" id="unidades">
            <div class="card-header"><h2 class="card-title">Unidades de medida</h2></div>
            <div class="card-body">@include('cuentas.unidades')</div>
        </section>
        @if(\Illuminate\Support\Facades\Route::has('cuenta.clave'))
        <section class="card cuenta-tarjeta">
            <div class="card-header"><h2 class="card-title">Cambiar contraseña</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('cuenta.clave') }}">
                    @csrf @method('PUT')
                    <label for="clave-actual" class="form-label">Contraseña actual</label>
                    <input id="clave-actual" name="current_password" class="form-control mb-3" type="password" autocomplete="current-password" required>
                    <label for="clave-nueva" class="form-label">Nueva contraseña</label>
                    <input id="clave-nueva" name="password" class="form-control mb-3" type="password" autocomplete="new-password" minlength="12" required aria-describedby="ayuda-clave">
                    <p id="ayuda-clave">Usa al menos 12 caracteres. Al guardar, se cerrarán tus otras sesiones.</p>
                    <label for="clave-confirmar" class="form-label">Repite la nueva contraseña</label>
                    <input id="clave-confirmar" name="password_confirmation" class="form-control mb-4" type="password" autocomplete="new-password" minlength="12" required>
                    <button class="btn btn-primary" type="submit">Guardar contraseña</button>
                </form>
            </div>
        </section>
        @endif
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

    </div>
</div>
@endsection
@push('scripts')
@include('cuentas.configuracion-unidades')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="module" src="{{ asset('js/cuentas/ubicacion.js') }}"></script>
@endpush
