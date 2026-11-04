@extends('layouts.acceso')
@section('titulo', 'Recuperar acceso')
@section('antetitulo', 'Recuperar acceso')
@section('encabezado')¿Olvidaste tu <em>contraseña?</em>@endsection
@section('descripcion', 'Escribe el correo de tu cuenta para solicitar un enlace de recuperación.')
@section('formulario')
@include('cuentas.estado')
<form method="post" action="{{ route('password.email') }}" class="auth-form">
    @csrf
    <div class="auth-field"><label for="email">Correo electrónico</label><input class="@error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="email" placeholder="tu@correo.bo" value="{{ old('email') }}" maxlength="254" required autofocus @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>@error('email')<span class="auth-error-msg" id="error-email" role="alert">{{ $message }}</span>@enderror</div>
    <button type="submit" class="auth-btn">Enviar enlace →</button>
</form>
<p class="auth-footer-text"><a href="{{ route('login') }}" class="auth-link">← Volver al inicio de sesión</a></p>
@endsection
