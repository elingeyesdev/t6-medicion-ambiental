@extends('layouts.app')
@section('titulo', 'Usuarios')
@section('clase-cuerpo', 'pagina-cuentas')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/cuentas.css') }}">
<link rel="stylesheet" href="{{ asset('css/paginas/usuarios.css') }}?v=1">
@endpush
@section('contenido')
<div class="usuarios-panel cuentas-contenido">
    <header class="usuarios-cabecera">
        <div>
            <h1>Gestión de usuarios</h1>
            <p>Busca cuentas, cambia sus permisos o suspende su acceso.</p>
        </div>
        <dl class="usuarios-resumen">
            <div><dt>{{ request('buscar') ? 'Resultados' : 'Cuentas' }}</dt><dd>{{ $usuarios->total() }}</dd></div>
            <div><dt>Activas</dt><dd>{{ $activas }}</dd></div>
            <div><dt>Suspendidas</dt><dd>{{ $usuarios->total() - $activas }}</dd></div>
        </dl>
    </header>
    @include('cuentas.estado')
    <section class="card cuenta-tarjeta usuarios-directorio" aria-labelledby="directorio-titulo">
        <div class="card-header cuenta-fila">
            <h2 id="directorio-titulo">Directorio de cuentas</h2>
            <form method="get" action="{{ route('usuarios') }}" class="cuenta-busqueda">
                <div>
                    <label for="buscar" class="form-label">Nombre o correo</label>
                    <input id="buscar" name="buscar" type="search" class="form-control" maxlength="100" value="{{ request('buscar') }}">
                </div>
                <button class="btn btn-outline-primary" type="submit">Buscar</button>
                @if(request('buscar'))
                    <a class="btn btn-outline-primary" href="{{ route('usuarios') }}">Limpiar</a>
                @endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="cuenta-tabla">
                <thead><tr><th scope="col">Cuenta</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                @forelse($usuarios as $usuario)
                    @php($propia = $usuario->is(auth()->user()))
                    <tr>
                        <td>
                            <div class="cuenta-identidad">
                                <span class="cuenta-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($usuario->name, 0, 2)) }}</span>
                                <div><strong>{{ $usuario->name }}</strong><small>{{ $usuario->email }}</small></div>
                            </div>
                        </td>
                        <td><span class="cuenta-rol {{ $usuario->rol === \App\Enums\RolCuenta::Administrador ? 'usuarios-rol-admin' : '' }}">{{ $usuario->rol->nombre() }}</span></td>
                        <td><span class="cuenta-estado {{ $usuario->activo ? '' : 'suspendida' }}">{{ $usuario->activo ? 'Activa' : 'Suspendida' }}</span></td>
                        <td>
                            @if($propia)
                                <span class="cuenta-subtitulo">Tu cuenta</span>
                            @else
                                <details class="usuarios-acciones">
                                    <summary aria-label="Gestionar cuenta de {{ $usuario->name }}">Gestionar</summary>
                                    <form method="post" action="{{ route('usuarios.rol', $usuario) }}" class="cuenta-rol-form">
                                        @csrf @method('PATCH')
                                        <div>
                                            <label class="form-label" for="rol-{{ $usuario->id }}">Asignar rol</label>
                                            <select class="form-select" name="rol" id="rol-{{ $usuario->id }}">
                                                @foreach($roles as $rol)
                                                    <option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>{{ $rol->nombre() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button class="btn btn-primary" type="submit">Guardar rol</button>
                                    </form>
                                    <form method="post" action="{{ route('usuarios.estado', $usuario) }}" class="cuenta-controles">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="activo" value="{{ $usuario->activo ? '0' : '1' }}">
                                        <button class="btn {{ $usuario->activo ? 'btn-outline-danger' : 'btn-outline-primary' }}" type="submit">{{ $usuario->activo ? 'Suspender cuenta' : 'Reactivar cuenta' }}</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="cuenta-vacio">No se encontraron cuentas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($usuarios->hasPages())
            <nav class="cuenta-paginacion" aria-label="Páginas de usuarios">
                @if($usuarios->previousPageUrl())<a class="btn btn-outline-primary" href="{{ $usuarios->previousPageUrl() }}">Anterior</a>@endif
                <span>Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }}</span>
                @if($usuarios->nextPageUrl())<a class="btn btn-outline-primary" href="{{ $usuarios->nextPageUrl() }}">Siguiente</a>@endif
            </nav>
        @endif
    </section>
    <section class="usuarios-permisos" aria-labelledby="roles-titulo">
        <h2 id="roles-titulo">Permisos por rol</h2>
        <dl>
            <div><dt>Administrador</dt><dd>Administra cuentas, registra mediciones, ejecuta simulaciones, gestiona alertas y crea reportes y plantillas.</dd></div>
            <div><dt>Visualizador</dt><dd>Consulta mapas, análisis ambientales, mediciones y reportes. Puede gestionar sus propias preferencias de avisos.</dd></div>
        </dl>
    </section>
</div>
@endsection
