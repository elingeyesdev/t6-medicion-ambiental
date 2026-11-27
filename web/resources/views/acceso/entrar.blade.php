@extends('layouts.publico')
@section('titulo', 'Ingresar')
@section('clase-publica', 'pagina-acceso')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/auth-envirosense.css') }}?v=paleta-geosur-2">
<link rel="stylesheet" href="{{ asset('css/paginas/entrar.css') }}?v=6">
@endpush
@section('contenido')
@php
    $fotos = [
        ['amboro.jpg', 'Parque Nacional Amboró', 'Marcojairo', 'https://commons.wikimedia.org/wiki/File:Parque_Nacional_Ambor%C3%B3.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/', 1280, 720],
        ['lomas.webp', 'Lomas de Arena', 'Gabriel Millos', 'https://commons.wikimedia.org/wiki/File:Lomas_de_Arena_-_Santa_Cruz,_Bolivia.jpg', 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0', 1280, 960],
        ['tucavaca.webp', 'Valle de Tucavaca', 'Ludwing Melgar Egüez', 'https://commons.wikimedia.org/wiki/File:Mirador_Valle_de_Tucavaca.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 1280, 640],
        ['itenez.webp', 'Río Iténez', 'Gabriela Villanueva', 'https://commons.wikimedia.org/wiki/File:R%C3%ADo_Itenez.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 1280, 853],
        ['amboro-bosque-nuboso.jpg', 'Bosque nuboso del Amboró', 'Ángel M. Felicísimo', 'https://commons.wikimedia.org/wiki/File:Bosque_nuboso_-_Parque_Nacional_Ambor%C3%B3.jpg', 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0/', 1024, 768],
        ['cascada.webp', 'Jardín de las Delicias', 'Ruditaly', 'https://commons.wikimedia.org/wiki/File:Ca%C3%B1%C3%B3n_del_Jard%C3%ADn_de_las_Delicias_-_El_Torno,_Bolivia.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 1125, 1500],
        ['serrania.webp', 'Serranía de Santiago', 'Ruditaly', 'https://commons.wikimedia.org/wiki/File:Acantilado_en_la_Serran%C3%ADa_de_Santiago_-_Santiago_de_Chiquitos,_Bolivia.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 1280, 720],
        ['noel-kempff.webp', 'Parque Nacional Noel Kempff Mercado', 'Maral Hurtado Miranda', 'https://commons.wikimedia.org/wiki/File:Blue_sky_and_blue_water.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 1280, 853],
        ['dunas.webp', 'Lomas de Arena al atardecer', 'Marco Ariel AgredaVillarroel', 'https://commons.wikimedia.org/wiki/File:Atardecer_en_el_Parque_Departamental_y_%C3%81rea_Natural_de_Manejo_Integrado_Lomas_de_Arena_20180929_183204.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 1280, 960],
        ['ichilo.webp', 'Río Ichilo', 'WillyMontaño', 'https://commons.wikimedia.org/wiki/File:Cajones_del_R%C3%ADo_Ichilo.jpg', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 844, 1500],
    ];
@endphp
<main id="contenido" class="login-page" tabindex="-1">
    <figure class="login-landscape">
        @foreach($fotos as [$archivo, $lugar, $autor, $fuente, $licencia, $enlaceLicencia, $ancho, $alto])
            <img src="{{ asset('img/acceso/'.$archivo) }}" @if($loop->first) class="foto-activa" fetchpriority="high" @else fetchpriority="low" @endif data-foto width="{{ $ancho }}" height="{{ $alto }}" decoding="async" alt="">
        @endforeach
        <figcaption>
            @foreach($fotos as [$archivo, $lugar, $autor, $fuente, $licencia, $enlaceLicencia])
                <div class="login-photo-caption" data-credito @unless($loop->first) inert @endunless>
                    <strong>{{ $lugar }}</strong>
                    <span>Santa Cruz, Bolivia</span>
                    <small><a href="{{ $fuente }}">{{ $autor }}</a> · <a href="{{ $enlaceLicencia }}">{{ $licencia }}</a></small>
                </div>
            @endforeach
        </figcaption>
    </figure>
    <section class="login-form-col" aria-labelledby="auth-heading">
        <div class="login-header">
            <a class="login-brand" href="{{ route('inicio') }}" aria-label="GeoSur, inicio">
                <img src="{{ asset('img/marca/geosur-simbolo.svg') }}" alt="" width="38" height="38">
                <span>GeoSur</span>
            </a>
            <a class="login-return" href="{{ route('inicio') }}"><x-publico.enviro-icono nombre="arrow" /> Volver al inicio</a>
        </div>
        <div class="login-form-wrap">
            <h1 class="auth-heading" id="auth-heading">Inicia sesión</h1>
            <p class="auth-subheading">Accede al mapa, las mediciones y el análisis ambiental de GeoSur.</p>
            @if(session('estado'))<div class="auth-success" role="status">{{ session('estado') }}</div>@endif
            <form method="post" action="{{ route('acceso.entrar') }}" class="auth-form">
                @csrf
                <div class="auth-field"><label for="email">Correo electrónico</label><input class="@error('email') is-invalid @enderror" id="email" name="email" type="email" autocomplete="username" placeholder="tu@correo.bo" value="{{ old('email') }}" maxlength="254" required autofocus @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>@error('email')<span id="error-email" class="auth-error-msg" role="alert">{{ $message }}</span>@enderror</div>
                <div class="auth-field"><div class="auth-password-label"><label for="password">Contraseña</label><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div><input class="@error('password') is-invalid @enderror" id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="error-password" @enderror>@error('password')<span id="error-password" class="auth-error-msg" role="alert">{{ $message }}</span>@enderror</div>
                <button type="submit" class="auth-btn">Ingresar <x-publico.enviro-icono nombre="arrow" /></button>
            </form>
            <p class="auth-footer-text">¿No tienes cuenta? <a href="{{ route('register') }}" class="auth-link">Solicita acceso</a></p>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script type="module" src="{{ asset('js/acceso-fotos.js') }}?v=3"></script>
@endpush
