@extends('layouts.acceso')
@section('titulo', 'Ingresar')
@section('encabezado', 'Bienvenido de vuelta.')
@section('formulario')
<form method="post" action="{{ route('acceso.entrar') }}">
    @csrf
    <div class="mb-3"><label class="form-label" for="email">Correo electrónico</label><input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" maxlength="254" required autofocus @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>@error('email')<div id="error-email" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror</div>
    <div class="mb-4"><label class="form-label" for="password">Contraseña</label><input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="error-password" @enderror>@error('password')<div id="error-password" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror</div>
    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
</form>
<p class="acceso-alternativa">¿No tienes cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
@endsection
