@extends('layouts.acceso')
@section('titulo', 'Ingresar')
@section('antetitulo', 'Acceso')
@section('encabezado', 'Bienvenido de vuelta.')
@section('descripcion', 'Ingresa tus credenciales para continuar con la observación.')
@section('formulario')
@if(session('estado'))<div class="auth-success" role="status">{{ session('estado') }}</div>@endif
<form method="post" action="{{ route('acceso.entrar') }}" class="auth-form">
    @csrf
    <div class="auth-field"><label for="email">Correo electrónico</label><input class="@error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="username" placeholder="tu@correo.bo" value="{{ old('email') }}" maxlength="254" required autofocus @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>@error('email')<span id="error-email" class="auth-error-msg" role="alert">{{ $message }}</span>@enderror</div>
    <div class="auth-field"><div class="auth-password-label"><label for="password">Contraseña</label><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div><input class="@error('password') is-invalid @enderror" id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="error-password" @enderror>@error('password')<span id="error-password" class="auth-error-msg" role="alert">{{ $message }}</span>@enderror</div>
    <button type="submit" class="auth-btn">Ingresar →</button>
</form>
<p class="auth-footer-text">¿No tienes cuenta? <a href="{{ route('register') }}" class="auth-link">Solicita acceso</a></p>
@endsection
