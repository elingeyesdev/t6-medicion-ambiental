@extends('layouts.acceso')
@section('titulo', 'Crear cuenta')
@section('encabezado', 'Crea tu cuenta.')
@section('formulario')
<form method="post" action="{{ route('acceso.registrar') }}">
    @csrf
    <div class="mb-3"><label class="form-label" for="name">Nombre</label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" autocomplete="name" value="{{ old('name') }}" maxlength="100" required autofocus @error('name') aria-invalid="true" aria-describedby="error-name" @enderror>@error('name')<div class="invalid-feedback" id="error-name" role="alert">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label" for="email">Correo electrónico</label><input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" maxlength="254" required @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>@error('email')<div class="invalid-feedback" id="error-email" role="alert">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label" for="password">Contraseña</label><input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required aria-describedby="ayuda-password @error('password') error-password @enderror" @error('password') aria-invalid="true" @enderror><div class="form-hint" id="ayuda-password">Al menos 12 caracteres.</div>@error('password')<div class="invalid-feedback" id="error-password" role="alert">{{ $message }}</div>@enderror</div>
    <div class="mb-4"><label class="form-label" for="password_confirmation">Repetir contraseña</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
    <button type="submit" class="btn btn-primary w-100">Crear cuenta</button>
</form>
<p class="acceso-alternativa">¿Ya tienes cuenta? <a href="{{ route('login') }}">Ingresar</a></p>
@endsection
