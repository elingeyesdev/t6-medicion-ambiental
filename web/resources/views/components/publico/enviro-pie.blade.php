<footer class="landing-footer">
    <div class="enviro-container landing-footer-inner">
        <a class="landing-brand" href="{{ route('inicio') }}"><img src="{{ asset('img/marca/geosur-simbolo.svg') }}" alt="" width="40" height="32"><span>Geo<span>Sur</span><small>© {{ date('Y') }} · Observación ambiental</small></span></a>
        <nav aria-label="Enlaces al pie"><a href="#plataforma">Plataforma</a><a href="#metricas">Análisis disponibles</a><a href="#contacto">Acceso</a></nav>
        <a class="landing-footer-access" href="{{ route('login') }}">Acceder al sistema →</a>
    </div>
</footer>
