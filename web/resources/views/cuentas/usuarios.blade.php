@extends('layouts.app')
@section('titulo', 'Usuarios')
@section('clase-cuerpo', 'pagina-cuentas')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/cuentas.css') }}">@endpush
@section('contenido')
<div class="cuentas-contenido">
    <header class="cuenta-cabecera">
        <div><div class="cuenta-ceja">{{ $usuarios->total() }} usuarios {{ request('buscar') ? 'en la búsqueda' : 'registrados' }}</div><h1>Gestión de <em>usuarios</em></h1><p>Administra cuentas y roles desde este panel exclusivo para Administradores.</p></div>
        <span class="cuenta-etiqueta">Admin</span>
    </header>
    @include('cuentas.estado')
    <div class="cuenta-kpis">
        <div class="cuenta-kpi"><span>{{ request('buscar') ? 'Usuarios encontrados' : 'Usuarios totales' }}</span><strong>{{ $usuarios->total() }}</strong></div>
        <div class="cuenta-kpi"><span>Cuentas habilitadas</span><strong>{{ $usuarios->getCollection()->where('activo', true)->count() }}</strong><small>De {{ $usuarios->count() }} cuentas en esta página</small></div>
    </div>
    <div class="cuentas-directorio">
        <section class="card cuenta-tarjeta">
            <div class="card-header cuenta-fila">
                <div><h2>Directorio de <em>usuarios</em></h2><div class="cuenta-subtitulo">{{ $usuarios->count() }} resultados mostrados</div></div>
                <form method="get" action="{{ route('usuarios') }}" class="cuenta-busqueda">
                    <div><label for="buscar" class="form-label">Nombre o correo</label><input id="buscar" name="buscar" type="search" class="form-control" maxlength="100" placeholder="Buscar usuario..." value="{{ request('buscar') }}"></div>
                    <button class="btn btn-outline-primary" type="submit">Buscar</button>
                </form>
            </div>
            <div class="table-responsive"><table class="cuenta-tabla">
                <thead><tr><th scope="col">Usuario</th><th scope="col">Rol actual</th><th scope="col">Estado</th><th scope="col">Asignar nuevo rol</th></tr></thead>
                <tbody>
                @forelse($usuarios as $usuario)
                    <tr>
                        <td><div class="cuenta-identidad"><span class="cuenta-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($usuario->name, 0, 2)) }}</span><div>{{ $usuario->name }}<small>{{ $usuario->email }}</small></div></div></td>
                        <td><span class="cuenta-rol">{{ $usuario->rol->nombre() }}</span></td>
                        <td>
                            <span class="cuenta-estado {{ $usuario->activo ? '' : 'suspendida' }}">{{ $usuario->activo ? 'Activa' : 'Suspendida' }}</span>
                            @unless($usuario->is(auth()->user()))
                            <form method="post" action="{{ route('usuarios.estado', $usuario) }}" class="cuenta-controles">
                                @csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $usuario->activo ? '0' : '1' }}">
                                <button class="btn {{ $usuario->activo ? 'btn-outline-danger' : 'btn-outline-primary' }}" type="submit">{{ $usuario->activo ? 'Suspender cuenta' : 'Reactivar cuenta' }}</button>
                            </form>
                            @endunless
                        </td>
                        <td>
                            @if($usuario->is(auth()->user()))<span class="cuenta-subtitulo">Tu cuenta · {{ $usuario->rol->nombre() }}</span>
                            @else
                            <form method="post" action="{{ route('usuarios.rol', $usuario) }}" class="cuenta-rol-form">
                                @csrf @method('PATCH')
                                <div><label class="form-label" for="rol-{{ $usuario->id }}">Rol</label><select class="form-select" name="rol" id="rol-{{ $usuario->id }}">@foreach($roles as $rol)<option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>{{ $rol->nombre() }}</option>@endforeach</select></div>
                                <button class="btn btn-primary" type="submit">Guardar rol</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty<tr><td colspan="4" class="cuenta-vacio">No encontré cuentas con esa búsqueda.</td></tr>@endforelse
                </tbody>
            </table></div>
        </section>
        <aside class="card cuenta-tarjeta">
            <div class="card-header"><div><h2>Permisos <em>(Informativo)</em></h2><div class="cuenta-subtitulo">Jerarquía de acceso</div></div></div>
            <div class="card-body cuenta-permisos">
                @foreach($roles as $rol)
                <strong>{{ $rol->nombre() }}</strong>
                <p>{{ match($rol->value) { 'admin' => 'Acceso total al sistema y administración de cuentas.', 'analista' => 'Ejecuta simulaciones, genera reportes y gestiona alertas.', default => 'Consulta el mapa, los datos y los reportes.' } }}</p>
                @endforeach
            </div>
        </aside>
    </div>
    @if($usuarios->hasPages())<nav class="cuenta-paginacion" aria-label="Páginas de usuarios">
        @if($usuarios->previousPageUrl())<a class="btn btn-outline-primary" href="{{ $usuarios->previousPageUrl() }}">Anterior</a>@endif
        <span>Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }}</span>
        @if($usuarios->nextPageUrl())<a class="btn btn-outline-primary" href="{{ $usuarios->nextPageUrl() }}">Siguiente</a>@endif
    </nav>@endif
</div>
@endsection
