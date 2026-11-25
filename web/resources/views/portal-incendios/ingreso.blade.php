<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso de brigada · Avisos de incendio</title>
    <link rel="icon" href="{{ asset('img/marca/geosur-simbolo.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/envirosense/fuentes.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal-incendios.css') }}?v=acceso-brigada">
</head>
<body class="pagina-acceso-brigada">
    <header class="barra">
        <div class="marco barra-interior">
            <a class="marca" href="{{ route('portal-incendios.index') }}"><img src="{{ asset('img/marca/geosur-simbolo.svg') }}" alt="" width="35" height="35"> Avisos de incendio</a>
            <a class="boton boton-claro" href="{{ route('portal-incendios.index') }}">Ver avisos públicos</a>
        </div>
    </header>
    <main class="marco acceso-marco">
        <div class="acceso-paisaje">
            <div>
                <p>Santa Cruz, Bolivia</p>
                <h2>Verificación en campo</h2>
                <p>Consulta lo que detectó el satélite, toma un aviso y registra lo que encontró la brigada.</p>
            </div>
            <p class="acceso-credito">Serranía de Santiago · <a href="https://commons.wikimedia.org/wiki/File:Acantilado_en_la_Serran%C3%ADa_de_Santiago_-_Santiago_de_Chiquitos,_Bolivia.jpg" target="_blank" rel="noopener noreferrer">Ruditaly</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a></p>
        </div>
        <section class="acceso-formulario" aria-labelledby="titulo-ingreso">
            <h1 id="titulo-ingreso">Acceso de brigada</h1>
            <p>Ingresa con tu cuenta del portal de incendios.</p>
            @if(session('solicitud_enviada'))<p class="nota-operativa" role="status">{{ session('solicitud_enviada') }}</p>@endif
            <form action="{{ route('portal-incendios.brigada.entrar') }}" method="post">
                @csrf
                @if($aviso)<input type="hidden" name="aviso" value="{{ $aviso }}">@endif
                <div>
                    <label for="brigada-email">Correo electrónico</label>
                    <input id="brigada-email" name="email" type="email" autocomplete="username" maxlength="254" required value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="brigada-email-error" @enderror>
                    @error('email')<p id="brigada-email-error" class="acceso-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="brigada-clave">Contraseña</label>
                    <input id="brigada-clave" name="password" type="password" autocomplete="current-password" maxlength="255" required @error('password') aria-invalid="true" aria-describedby="brigada-clave-error" @enderror>
                    @error('password')<p id="brigada-clave-error" class="acceso-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <button class="boton boton-principal" type="submit">Entrar al panel</button>
            </form>
            <p class="solicitar-acceso"><a href="{{ route('portal-incendios.solicitud', $aviso ? ['aviso' => $aviso] : []) }}">Crear cuenta</a></p>
        </section>
    </main>
</body>
</html>
