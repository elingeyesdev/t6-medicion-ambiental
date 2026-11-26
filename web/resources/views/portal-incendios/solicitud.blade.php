<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta · Avisos de incendio</title>
    <link rel="icon" href="{{ asset('img/marca/geosur-simbolo.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/envirosense/fuentes.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal-incendios.css') }}?v=solicitud-brigada">
</head>
<body>
    <header class="barra">
        <div class="marco barra-interior">
            <a class="marca" href="{{ route('portal-incendios.index') }}"><img src="{{ asset('img/marca/geosur-simbolo.svg') }}" alt="" width="35" height="35"> Avisos de incendio</a>
            <a class="boton boton-claro" href="{{ route('portal-incendios.brigada', $aviso ? ['aviso' => $aviso] : []) }}">Iniciar sesión</a>
        </div>
    </header>
    <main class="marco">
        <section class="panel acceso-formulario solicitud-cuenta" aria-labelledby="titulo-solicitud">
            <h1 id="titulo-solicitud">Crear cuenta</h1>
            <p>Crea una cuenta para usar el panel de avisos de la demo.</p>
            <form method="post" action="{{ route('portal-incendios.solicitud.registrar', $aviso ? ['aviso' => $aviso] : []) }}">
                @csrf
                <div class="solicitud-campos">
                    <div>
                        <label for="sol-nombre">Nombre completo</label>
                        <input id="sol-nombre" name="nombre" autocomplete="name" maxlength="120" required value="{{ old('nombre') }}" @error('nombre') aria-invalid="true" aria-describedby="sol-nombre-error" @enderror>
                        @error('nombre')<p class="acceso-error" id="sol-nombre-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sol-email">Correo electrónico</label>
                        <input id="sol-email" type="email" name="email" autocomplete="email" maxlength="254" required value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="sol-email-error" @enderror>
                        @error('email')<p class="acceso-error" id="sol-email-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sol-clave">Contraseña</label>
                        <input id="sol-clave" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required aria-describedby="sol-clave-ayuda @error('password') sol-clave-error @enderror" @error('password') aria-invalid="true" @enderror>
                        <small id="sol-clave-ayuda">Al menos 12 caracteres.</small>
                        @error('password')<p class="acceso-error" id="sol-clave-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sol-confirmar">Confirmar contraseña</label>
                        <input id="sol-confirmar" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" required>
                    </div>
                </div>
                <button class="boton boton-principal" type="submit">Crear cuenta y entrar</button>
            </form>
        </section>
    </main>
</body>
</html>
