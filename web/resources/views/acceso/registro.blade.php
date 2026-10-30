@extends('layouts.acceso')
@section('titulo', 'Crear cuenta')
@section('antetitulo', 'Solicitar acceso')
@section('encabezado')Crear una <em>cuenta.</em>@endsection
@section('descripcion', 'Completa el formulario para sumarte a la observación ambiental.')
@push('estilos')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush
@section('formulario')
@include('cuentas.estado')
@php($canalesAcceso = app(\App\Services\Notificaciones\CanalesAvisos::class))
<form method="post" action="{{ route('acceso.registrar') }}" class="auth-form" data-enviro-registro>
    @csrf
    <div class="auth-field"><label for="name">Nombre completo</label><input class="@error('name') is-invalid @enderror" id="name" name="name" autocomplete="name" placeholder="Tu nombre y apellido" value="{{ old('name') }}" maxlength="100" required autofocus @error('name') aria-invalid="true" @enderror aria-describedby="error-name"><span class="auth-error-msg" id="error-name" data-error="name" role="alert" @if(!$errors->has('name')) hidden @endif>{{ $errors->first('name') }}</span></div>
    <div class="auth-field"><label for="email">Correo electrónico</label><input class="@error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="username" placeholder="tu@correo.bo" value="{{ old('email') }}" maxlength="254" required @error('email') aria-invalid="true" @enderror aria-describedby="error-email"><span class="auth-error-msg" id="error-email" data-error="email" role="alert" @if(!$errors->has('email')) hidden @endif>{{ $errors->first('email') }}</span></div>
    <div class="auth-row">
        <div class="auth-field"><label for="password">Contraseña</label><input class="@error('password') is-invalid @enderror" id="password" name="password" type="password" placeholder="••••••••" minlength="12" autocomplete="new-password" required aria-describedby="ayuda-password error-password" @error('password') aria-invalid="true" @enderror><span class="form-hint" id="ayuda-password">Al menos 12 caracteres.</span><span class="auth-error-msg" id="error-password" data-error="password" role="alert" @if(!$errors->has('password')) hidden @endif>{{ $errors->first('password') }}</span></div>
        <div class="auth-field"><label for="password_confirmation">Confirmar</label><input id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" minlength="12" autocomplete="new-password" required aria-describedby="error-password_confirmation"><span class="auth-error-msg" id="error-password_confirmation" data-error="password_confirmation" role="alert" hidden></span></div>
    </div>
    <fieldset class="auth-extras" disabled data-registro-extras>
        <legend class="auth-divider">Ubicación geográfica (opcional)</legend>
        <p class="auth-location-help" id="ayuda-ubicacion">Haz clic en el mapa para elegir tu ubicación de referencia. También puedes usar las coordenadas.</p>
        <div id="registro-mapa" class="auth-location-map" tabindex="0" role="region" aria-label="Elegir ubicación de referencia" aria-describedby="ayuda-ubicacion"></div>
        <div class="auth-location-state"><span id="estado-ubicacion" role="status">Sin ubicación seleccionada.</span><button class="auth-text-button" id="quitar-ubicacion" type="button" hidden>Limpiar ubicación</button></div>
        <details class="auth-coordinates"><summary>Introducir coordenadas</summary><div class="auth-row"><div class="auth-field"><label for="latitud">Latitud</label><input type="number" id="latitud" name="latitud" min="-90" max="90" step="any" placeholder="-17.7833" value="{{ old('latitud') }}"></div><div class="auth-field"><label for="longitud">Longitud</label><input type="number" id="longitud" name="longitud" min="-180" max="180" step="any" placeholder="-63.1821" value="{{ old('longitud') }}"></div></div></details>
        <div class="auth-divider">Preferencias de notificación</div>
        <p class="auth-info-banner" data-notif-help>Selecciona tu ubicación para habilitar los canales disponibles. Los avisos requieren tu consentimiento.</p>
        <div class="auth-notif-options">
            @foreach(['email' => 'Correo electrónico','whatsapp' => 'WhatsApp','telegram' => 'Telegram'] as $canal => $nombre)
            <label class="auth-checkbox-label"><input type="checkbox" name="notif_{{ $canal }}" value="1" data-canal="{{ $canal }}" data-disponible="{{ $canalesAcceso->disponible($canal) ? '1' : '0' }}" @checked(old('notif_'.$canal)) disabled><span>Recibir por {{ $nombre }} @if(!$canalesAcceso->disponible($canal))<small>· No disponible</small>@endif</span></label>
            @if($canal !== 'email')
            <div class="auth-field auth-notif-destination" data-destino="{{ $canal }}" hidden><label for="{{ $canal }}_destino">{{ $canal === 'whatsapp' ? 'Número con código de país' : 'Chat ID privado de Telegram' }}</label><input id="{{ $canal }}_destino" name="{{ $canal }}_destino" type="text" inputmode="{{ $canal === 'whatsapp' ? 'tel' : 'numeric' }}" maxlength="{{ $canal === 'whatsapp' ? 16 : 20 }}" pattern="{{ $canal === 'whatsapp' ? '[+]?[1-9][0-9]{7,14}' : '[1-9][0-9]{3,19}' }}" placeholder="{{ $canal === 'whatsapp' ? '+59170000000' : '123456789' }}" value="{{ old($canal.'_destino') }}" disabled></div>
            @endif
            @endforeach
            <label class="auth-checkbox-label auth-consent" data-consentimiento hidden><input name="consentimiento" type="checkbox" value="1" disabled><span>Deseo recibir avisos en los canales seleccionados.</span></label>
        </div>
    </fieldset>
    <noscript><p class="auth-info-banner">Puedes crear tu cuenta y configurar la ubicación y los avisos desde Mi cuenta al ingresar.</p></noscript>
    <button type="submit" class="auth-btn">Crear cuenta</button>
</form>
<p class="auth-footer-text">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="auth-link">Inicia sesión</a></p>
@endsection
@push('scripts')
<script type="application/json" id="configuracion-mapa">@json(\App\Support\ConfiguracionMapa::paraWeb())</script>
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="module" src="{{ asset('assets/envirosense-publico/registro.js') }}"></script>
@endpush
