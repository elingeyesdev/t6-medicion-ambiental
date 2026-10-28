@extends('layouts.acceso')
@section('titulo', 'Nueva contraseña')
@section('encabezado', 'Crea una nueva contraseña')
@section('formulario')
@include('cuentas.estado')
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3"><label class="form-label" for="email">Correo electrónico</label><input class="form-control" id="email" name="email" type="email" autocomplete="username" value="{{ old('email', $correo) }}" maxlength="254" required></div>
    <div class="mb-3"><label class="form-label" for="password">Nueva contraseña</label><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="ayuda-clave"><p id="ayuda-clave" class="mt-2 mb-0">Usa al menos 12 caracteres.</p></div>
    <div class="mb-4"><label class="form-label" for="password_confirmation">Repite la contraseña</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
    <button type="submit" class="btn btn-primary">Guardar contraseña</button>
</form>
@endsection
