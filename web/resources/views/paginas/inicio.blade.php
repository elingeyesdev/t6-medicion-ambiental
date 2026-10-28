@extends('layouts.publico')
@section('titulo', 'Inicio')
@section('clase-publica', 'pagina-inicio')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/inicio.css') }}">
@endpush
@section('contenido')
<x-publico.cabecera />
<main id="contenido" tabindex="-1">
    <x-publico.paisaje class="portada-hero">
        <div class="container-xl portada-presentacion">
            <h1>Explora<br>el territorio.</h1>
            <p>Focos de calor y sequías.<br>Consulta disponible en Santa Cruz, Bolivia.</p>
            <div class="acciones-publicas">
                <a class="btn btn-primary" href="{{ route('mapa') }}">Entrar al mapa <x-panel.icono nombre="arrow-right" /></a>
                @guest
                    <a class="btn btn-outline-light" href="{{ route('register') }}">Crear cuenta</a>
                @endguest
            </div>
        </div>
    </x-publico.paisaje>
    <section class="container-xl portada-modulos" id="explorar" aria-labelledby="titulo-explorar">
        <h2 id="titulo-explorar">Qué quieres consultar</h2>
        <div class="row g-4">
            @foreach(config('geocruz.modulos') as $id => $modulo)
                @if($modulo['habilitado'])
                    <div class="col-md-6">
                        <a class="card modulo-publico consulta-{{ $id }}" href="{{ route('modulos.mostrar', $id) }}" aria-label="Abrir {{ $modulo['nombre'] }}">
                            <div class="card-body">
                                <span class="consulta-icono"><x-panel.icono :nombre="$modulo['icono']" /></span>
                                <div>
                                    <h3>{{ $modulo['nombre'] }}</h3>
                                    <p>{{ $id === 'focos-calor' ? 'Ubica las detecciones de calor por fecha y lugar.' : 'Revisa las condiciones de lluvia de cada zona.' }}</p>
                                    <span class="consulta-enlace">
                                        {{ $id === 'focos-calor' ? 'Ver focos de calor' : 'Consultar sequías' }} <x-panel.icono nombre="arrow-right" />
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
</main>
<x-publico.pie />
@endsection
