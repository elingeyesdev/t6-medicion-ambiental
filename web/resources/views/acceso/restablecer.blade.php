@extends('layouts.acceso')
@section('titulo', 'Nueva contraseña')
@section('antetitulo', 'Restablecer acceso')
@section('encabezado')Crea una nueva <em>contraseña.</em>@endsection
@section('descripcion', 'Elige una contraseña para volver a ingresar a GeoSur.')
@section('formulario')
@include('cuentas.estado')
<form method="post" action="{{ route('password.update') }}" class="auth-form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="auth-field"><label for="email">Correo electrónico</label><input class="@error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="username" value="{{ old('email', $correo) }}" maxlength="254" required @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>@error('email')<span class="auth-error-msg" id="error-email" role="alert">{{ $message }}</span>@enderror</div>
    <div class="auth-field"><label for="password">Nueva contraseña</label><input class="@error('password') is-invalid @enderror" id="password" name="password" type="password" placeholder="••••••••" autocomplete="new-password" minlength="12" required aria-describedby="ayuda-clave @error('password') error-password @enderror" @error('password') aria-invalid="true" @enderror><span class="form-hint" id="ayuda-clave">Usa al menos 12 caracteres.</span>@error('password')<span class="auth-error-msg" id="error-password" role="alert">{{ $message }}</span>@enderror</div>
    <div class="auth-field"><label for="password_confirmation">Confirmar contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" autocomplete="new-password" minlength="12" required></div>
    <button type="submit" class="auth-btn">Guardar contraseña →</button>
</form>
<p class="auth-footer-text"><a href="{{ route('login') }}" class="auth-link">← Volver al inicio de sesión</a></p>
@endsection
