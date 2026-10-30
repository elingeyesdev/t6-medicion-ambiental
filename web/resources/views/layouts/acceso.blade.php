@extends('layouts.publico')
@section('clase-publica', 'pagina-acceso')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/auth-envirosense.css') }}?v=enviro-1">
@endpush
@section('contenido')
<main id="contenido" class="auth-page" tabindex="-1">
    <x-publico.enviro-auth-hero />
    <section class="auth-form-col" aria-labelledby="auth-heading">
        <div class="auth-form-wrap">
            <a class="auth-mobile-brand" href="{{ route('inicio') }}" aria-label="GeoSur, inicio">Geo<span>Sur</span></a>
            <p class="auth-eyebrow">@yield('antetitulo', 'Acceso')</p>
            <h1 class="auth-heading" id="auth-heading">@yield('encabezado')</h1>
            <p class="auth-subheading">@yield('descripcion')</p>
            @yield('formulario')
        </div>
    </section>
</main>
@endsection
