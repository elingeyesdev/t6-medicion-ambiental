@extends('layouts.app')
@section('titulo', 'Panel de monitoreo')

@section('contenido')
<div class="page-header mb-4">
    <div class="row align-items-center">
        <div class="col"><div class="page-pretitle">GeoCruz · Santa Cruz</div><h1 class="page-title">Panel de monitoreo</h1></div>
    </div>
</div>
<section aria-labelledby="titulo-consultas">
    <h2 id="titulo-consultas" class="h3 mb-2">Consultas ambientales</h2>
    <p class="text-secondary mb-4">Selecciona una consulta para ver el mapa, filtrar por territorio y revisar sus resultados.</p>
    <div class="row row-cards">
        @foreach($modulos as $id => $modulo)
            <div class="col-md-6">
                <article class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="avatar bg-primary-lt"><x-panel.icono :nombre="$modulo['icono']" /></span>
                            <div><h3 class="card-title mb-1">{{ $modulo['nombre'] }}</h3><span class="text-secondary">{{ $id === 'focos-calor' ? 'NASA FIRMS · VIIRS' : 'ERA5 · SPI-3' }}</span></div>
                        </div>
                        <p class="text-secondary">{{ $modulo['descripcion'] }}</p>
                    </div>
                    <div class="card-footer"><a class="btn btn-outline-primary w-100" href="{{ route('modulos.mostrar', $id) }}">Abrir {{ $modulo['nombre'] }} <x-panel.icono nombre="arrow-right" class="ms-auto" /></a></div>
                </article>
            </div>
        @endforeach
    </div>
</section>
<section class="card mt-4" aria-labelledby="titulo-territorio">
    <div class="card-header"><h2 class="card-title" id="titulo-territorio">Ámbito de consulta</h2></div>
    <div class="card-body"><p class="mb-2">Departamento de Santa Cruz, sus provincias y municipios.</p><p class="text-secondary mb-0">Cada consulta indica el período, la fuente y la cobertura de los resultados. La disponibilidad depende del producto y del territorio seleccionado.</p></div>
</section>
@endsection
