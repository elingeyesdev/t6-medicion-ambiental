@extends('layouts.app')
@section('titulo', 'Usuarios')
@section('clase-cuerpo', 'pagina-cuentas')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/cuentas.css') }}">@endpush
@section('contenido')
<div class="cuentas-contenido">
    <h1>Usuarios</h1>
    @include('cuentas.estado')
    <form method="get" action="{{ route('usuarios') }}" class="cuenta-busqueda">
        <div><label for="buscar" class="form-label">Nombre o correo</label><input id="buscar" name="buscar" type="search" class="form-control" maxlength="100" value="{{ request('buscar') }}"></div>
        <button class="btn btn-primary" type="submit">Buscar</button>
    </form>
    <p>{{ $usuarios->total() }} {{ $usuarios->total() === 1 ? 'cuenta' : 'cuentas' }}</p>
    <div class="cuenta-lista">
    @forelse($usuarios as $usuario)
        <article class="card cuenta-tarjeta">
            <div class="card-body">
                <div class="cuenta-fila"><h2>{{ $usuario->name }}</h2><span class="cuenta-estado {{ $usuario->activo ? '' : 'suspendida' }}">{{ $usuario->activo ? 'Activa' : 'Suspendida' }}</span></div>
                <p class="cuenta-correo">{{ $usuario->email }}</p>
                @if($usuario->is(auth()->user()))
                    <p class="mb-0">Tu cuenta · {{ $usuario->rol->nombre() }}</p>
                @else
                    <div class="cuenta-controles">
                        <form method="post" action="{{ route('usuarios.rol', $usuario) }}" class="cuenta-rol-form">
                            @csrf @method('PATCH')
                            <div><label class="form-label" for="rol-{{ $usuario->id }}">Rol</label><select class="form-select" name="rol" id="rol-{{ $usuario->id }}">@foreach($roles as $rol)<option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>{{ $rol->nombre() }}</option>@endforeach</select></div>
                            <button class="btn btn-primary" type="submit">Guardar rol</button>
                        </form>
                        <form method="post" action="{{ route('usuarios.estado', $usuario) }}">
                            @csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $usuario->activo ? '0' : '1' }}">
                            <button class="btn {{ $usuario->activo ? 'btn-outline-danger' : 'btn-outline-primary' }}" type="submit">{{ $usuario->activo ? 'Suspender cuenta' : 'Reactivar cuenta' }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <p>No encontré cuentas con esa búsqueda.</p>
    @endforelse
    </div>
    @if($usuarios->hasPages())<nav class="cuenta-paginacion" aria-label="Páginas de usuarios">
        @if($usuarios->previousPageUrl())<a class="btn btn-outline-primary" href="{{ $usuarios->previousPageUrl() }}">Anterior</a>@endif
        <span>Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }}</span>
        @if($usuarios->nextPageUrl())<a class="btn btn-outline-primary" href="{{ $usuarios->nextPageUrl() }}">Siguiente</a>@endif
    </nav>@endif
</div>
@endsection
