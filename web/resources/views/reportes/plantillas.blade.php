@extends('layouts.app')
@section('titulo', 'Plantillas de reportes')
@push('estilos')<link rel="stylesheet" href="{{ asset('css/paginas/reportes.css') }}">@endpush
@section('contenido')
<div class="rep-contenido">
    <header class="rep-cabecera"><h1>Plantillas de <span>Reportes</span></h1><p>Guarda tus filtros, columnas y gráficos para reutilizarlos en tus consultas.</p></header>
    <nav class="rep-pestanas" aria-label="Área de reportes"><a href="{{ route('reportes') }}">Dashboard Global</a><a href="{{ route('reportes', ['vista' => 'historial']) }}">Historial y Simulaciones</a><a href="{{ route('plantillas') }}" aria-current="page">Plantillas</a></nav>
    @include('cuentas.estado')
    <div class="rep-acciones">
        <a class="btn btn-outline-primary" href="{{ route('reportes') }}">Reportes</a>
        <a class="btn btn-outline-primary" href="{{ route('plantillas', ['archivadas' => $archivadas ? 0 : 1]) }}">{{ $archivadas ? 'Ver activas' : 'Ver archivadas' }}</a>
    </div>
    @can('reportes.crear')
    <details class="card mt-4" @if($errors->any()) open @endif>
        <summary class="card-header"><h2>Crear plantilla</h2></summary>
        <form action="{{ route('plantillas.guardar') }}" method="post" class="card-body">
            @csrf
            @include('reportes.formulario-plantilla', ['prefijo' => 'nueva', 'nombre' => old('nombre'), 'config' => ['columnas' => old('columnas', \App\Services\Reportes\PrepararReporte::PREDETERMINADAS), 'grafico' => old('grafico', true), 'tipo' => old('tipo', 'PERSONALIZADA'), 'condiciones' => old('condiciones', [])]])
            <button class="btn btn-primary" type="submit">Guardar plantilla</button>
        </form>
    </details>
    @endcan
    @forelse($plantillas as $p)
    <section class="card mt-4">
        <div class="card-header"><h2>{{ $p->nombre }}</h2></div>
        <div class="card-body">
            <p>{{ $tipos[$p->configuracion['tipo'] ?? 'PERSONALIZADA'] }} · {{ count($p->configuracion['condiciones'] ?? []) }} {{ count($p->configuracion['condiciones'] ?? []) === 1 ? 'filtro' : 'filtros' }}</p>
            <p>{{ implode(' · ', array_map(fn($c) => $columnas[$c], $p->configuracion['columnas'])) }}</p>
            @can('reportes.crear')
            @unless($archivadas)
            <details><summary>Editar plantilla</summary>
                <form method="post" action="{{ route('plantillas.actualizar', $p->id) }}" class="mt-3">
                    @csrf @method('PATCH')
                    @include('reportes.formulario-plantilla', ['prefijo' => $p->id, 'nombre' => $p->nombre, 'config' => $p->configuracion])
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </form>
            </details>
            @endunless
            <form class="mt-3" method="post" action="{{ route('plantillas.archivar', $p->id) }}">
                @csrf @method('DELETE')
                @if($archivadas)<input type="hidden" name="restaurar" value="1">@endif
                <button class="btn btn-outline-primary" type="submit">{{ $archivadas ? 'Restaurar' : 'Archivar' }}</button>
            </form>
            @endcan
        </div>
    </section>
    @empty<p class="mt-4">No hay plantillas {{ $archivadas ? 'archivadas' : 'guardadas' }}.</p>@endforelse
    {{ $plantillas->links() }}
</div>
@endsection
@push('scripts')<script type="module" src="{{ asset('js/reportes/plantillas.js') }}"></script>@endpush
