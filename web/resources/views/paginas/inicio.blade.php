@extends('layouts.publico')
@section('titulo', 'Inicio')
@section('clase-publica', 'pagina-inicio')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/paginas/inicio.css') }}?v=enviro-1">
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush
@section('contenido')
<x-publico.enviro-cabecera />
<main id="contenido" tabindex="-1">
    <section id="inicio" class="landing-hero" aria-labelledby="hero-heading">
        <div class="hero-background" aria-hidden="true"></div><div class="hero-shade" aria-hidden="true"></div><div class="hero-grid" aria-hidden="true"></div>
        <div class="enviro-container hero-content">
            <div class="hero-pill"><span aria-hidden="true"></span>Sistema de monitoreo ambiental</div>
            <h1 id="hero-heading">Transformamos datos climáticos en <span>decisiones estratégicas</span></h1>
            <p class="hero-description">Plataforma de análisis meteorológico y ambiental. Explora mapas, mediciones e historial, y consulta focos de calor y sequías en Santa Cruz, Bolivia.</p>
            <div class="hero-actions">
                <a class="enviro-button" href="{{ route('mapa') }}"><x-publico.enviro-icono nombre="arrow" />Explorar el Mapa</a>
                <a class="enviro-button enviro-button-outline" href="{{ route('login') }}">Iniciar Sesión</a>
            </div>
            <div class="hero-trust"><span><x-publico.enviro-icono nombre="radar" /> Mapa de monitoreo</span><span><x-publico.enviro-icono nombre="chart" /> Mediciones</span><span><x-publico.enviro-icono nombre="leaf" /> Análisis del territorio</span><span>Consulta fecha y procedencia</span></div>
        </div>
        <x-publico.enviro-wave />
    </section>
    <section class="landing-sources" aria-label="Herramientas disponibles">
        <div class="enviro-container">
            <p>Datos y herramientas para explorar el ambiente</p>
            <div class="source-list">
                @foreach([['radar','Mediciones','Variables y registros'],['chart','Historial','Evolución por fecha'],['flame','Focos de calor','Detecciones térmicas'],['rain','Sequías','Lluvia y SPI-3'],['screen','Reportes','Consulta según permisos']] as [$icono,$titulo,$detalle])
                <div class="source-item"><x-publico.enviro-icono :nombre="$icono" /><div><strong>{{ $titulo }}</strong><span>{{ $detalle }}</span></div></div>
                @endforeach
            </div>
        </div>
    </section>
    <section id="plataforma" class="landing-features landing-section" aria-labelledby="platform-heading">
        <div class="enviro-container">
            <div class="section-heading"><p class="section-eyebrow">Nuestra Plataforma</p><h2 id="platform-heading">Llevar el análisis ambiental al siguiente nivel</h2></div>
            <div class="feature-grid">
                @foreach([['01','radar','Mapa de Monitoreo','Explora puntos, variables y registros disponibles. Revisa la fecha y la fuente de cada medición.','Mapa · Mediciones',route('mapa')],['02','chart','Análisis del territorio','Consulta focos de calor y condiciones de sequía en Santa Cruz, Bolivia.','Focos de calor · Sequías','#metricas'],['03','shield','Historial y Avisos','Revisa el historial ambiental y configura las preferencias disponibles en tu cuenta.','Historial · Preferencias',route('cuenta')]] as [$numero,$icono,$titulo,$detalle,$etiqueta,$enlace])
                <a class="feature-card" href="{{ $enlace }}"><span class="feature-number">{{ $numero }}</span><x-publico.enviro-icono :nombre="$icono" /><h3>{{ $titulo }}</h3><p>{{ $detalle }}</p><span class="feature-tag">{{ $etiqueta }}</span></a>
                @endforeach
            </div>
        </div>
    </section>
    <section class="landing-map-section landing-section" aria-labelledby="coverage-heading">
        <div class="enviro-container">
            <div class="section-heading"><h2 id="coverage-heading">Una perspectiva del territorio</h2><p>Explora el mapa y consulta la cobertura de los datos disponibles.</p></div>
            <div class="landing-map-frame">
                <div id="portada-mapa" role="img" aria-label="Mapa de referencia de Sudamérica"></div>
                <div class="landing-map-caption"><span>EXPLORA GEOSUR</span><strong>Mapa de Monitoreo</strong><p>Datos y cobertura según fuente</p></div>
                <a class="enviro-button landing-map-access" href="{{ route('mapa') }}">Abrir mapa <x-publico.enviro-icono nombre="arrow" /></a>
            </div>
        </div>
    </section>
    <section id="metricas" class="landing-metrics landing-section" aria-labelledby="metrics-heading">
        <div class="enviro-container">
            <div class="section-heading"><p class="section-eyebrow">Variables y Análisis</p><h2 id="metrics-heading">Variables ambientales que consultamos</h2><p>Accede a los registros disponibles y a los análisis del territorio.</p></div>
            <div class="metric-grid">
                @foreach([['temperature','°C','Temperatura','Mediciones e historial',route('sensores'),'amber'],['drop','%','Humedad Relativa','Mediciones e historial',route('sensores'),'teal'],['flame','Focos','Detecciones de calor','Santa Cruz, Bolivia',route('modulos.mostrar','focos-calor'),'green'],['rain','SPI-3','Condiciones de sequía','Santa Cruz, Bolivia',route('modulos.mostrar','sequias'),'violet']] as [$icono,$valor,$titulo,$detalle,$enlace,$color])
                <a class="metric-card metric-{{ $color }}" href="{{ $enlace }}"><x-publico.enviro-icono :nombre="$icono" /><strong>{{ $valor }}</strong><h3>{{ $titulo }}</h3><p>{{ $detalle }}</p><span class="metric-link">Consultar datos <span aria-hidden="true">→</span></span></a>
                @endforeach
            </div>
        </div>
    </section>
    <section class="landing-why landing-section" aria-labelledby="why-heading">
        <div class="enviro-container why-grid">
            <div><p class="section-eyebrow">¿Por qué GeoSur?</p><h2 id="why-heading">Un sistema para comprender el ambiente</h2><p class="why-description">GeoSur reúne mapas, registros y análisis para consultar el territorio con contexto. Explora los datos y revisa su cobertura antes de interpretar los resultados.</p><div class="why-strip"><div><strong>Mapas</strong><span>Ubicación</span></div><div><strong>Historial</strong><span>Evolución</span></div><div><strong>Análisis</strong><span>Territorio</span></div></div></div>
            <div class="reason-grid">
                @foreach([['info','Datos con contexto','Consulta la fecha, las unidades y la procedencia de los datos.'],['shield','Preferencias de cuenta','Elige tu ubicación de referencia y los avisos disponibles.'],['screen','Desde tu navegador','Consulta la plataforma en escritorio, tablet o móvil.'],['chart','Análisis del territorio','Explora focos de calor y condiciones de sequía.']] as [$icono,$titulo,$detalle])
                <div class="reason-card"><x-publico.enviro-icono :nombre="$icono" /><h3>{{ $titulo }}</h3><p>{{ $detalle }}</p></div>
                @endforeach
            </div>
        </div>
    </section>
    <section class="landing-use-cases landing-section" aria-labelledby="cases-heading">
        <div class="enviro-container">
            <div class="section-heading"><p class="section-eyebrow">¿Para quién está diseñado?</p><h2 id="cases-heading">Una mirada compartida del ambiente</h2></div>
            <div class="use-case-grid">
                @foreach([['leaf','Sector Agrícola','Consulta las condiciones de lluvia y los análisis de sequía disponibles.','green'],['building','Gobiernos y Municipios','Explora el territorio y sus registros ambientales para apoyar el seguimiento local.','blue'],['chart','Instituciones e Investigadores','Revisa mediciones, historial y procedencia de los datos para tus consultas.','violet']] as [$icono,$titulo,$detalle,$color])
                <div class="use-case-card case-{{ $color }}"><span class="case-icon"><x-publico.enviro-icono :nombre="$icono" /></span><h3>{{ $titulo }}</h3><p>{{ $detalle }}</p></div>
                @endforeach
            </div>
        </div>
    </section>
    <section id="contacto" class="landing-cta landing-section" aria-labelledby="cta-heading">
        <div class="cta-inner"><p class="section-eyebrow">Empieza hoy</p><h2 id="cta-heading">Una nueva perspectiva.<br><span>Explora tu territorio.</span></h2><p>Accede a GeoSur para consultar mapas, mediciones y análisis ambientales.</p><div class="hero-actions"><a class="enviro-button" href="{{ route('login') }}"><x-publico.enviro-icono nombre="arrow" />Iniciar Sesión</a><a class="enviro-button enviro-button-outline" href="{{ route('register') }}">Crear una cuenta</a></div></div>
    </section>
</main>
<x-publico.enviro-pie />
@endsection
@push('scripts')
<script type="application/json" id="configuracion-mapa">@json(\App\Support\ConfiguracionMapa::paraWeb())</script>
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="module" src="{{ asset('assets/envirosense-publico/portada.js') }}"></script>
@endpush
