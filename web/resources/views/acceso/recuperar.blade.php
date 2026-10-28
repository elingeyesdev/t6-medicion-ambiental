@extends('layouts.acceso')
@section('titulo', 'Recuperar acceso')
@section('encabezado', 'Recupera tu acceso')
@section('formulario')
@include('cuentas.estado')
<p>Escribe tu correo y te enviaremos un enlace.</p>
<form method="post" action="{{ route('password.email') }}">
    @csrf
    <label class="form-label" for="email">Correo electrónico</label>
    <input class="form-control mb-4" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="254" required autofocus>
    <div class="acciones-publicas"><button type="submit" class="btn btn-primary">Recuperar acceso</button><a href="{{ route('login') }}" class="btn btn-outline-primary">Volver</a></div>
</form>
@endsection
