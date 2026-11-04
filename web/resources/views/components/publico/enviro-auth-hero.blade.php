<aside class="auth-hero" aria-label="Observación ambiental GeoSur">
    <div class="auth-hero-bg" aria-hidden="true"><img class="auth-hero-bg-img" src="{{ asset('img/envirosense/mapa_login.png') }}" alt="" fetchpriority="high"></div>
    <a class="auth-hero-brand" href="{{ route('inicio') }}" aria-label="GeoSur, inicio">
        <span class="mk"><img src="{{ asset('img/marca/geosur-simbolo.svg') }}" width="28" height="28" alt=""></span>
        <span><span class="nm">GeoSur</span><span class="tg">Observatorio ambiental · Territorio y clima</span></span>
    </a>
    <div class="auth-hero-mid">
        <div class="kicker">Observación ambiental</div>
        <h2>Observando el aire,<br>el agua, el clima y los<br>cambios del territorio<br><em>con perspectiva.</em></h2>
        <p>Mapas, mediciones y análisis ambientales para explorar el territorio. Consulta los datos disponibles, su fecha y su procedencia.</p>
    </div>
    <div class="auth-hero-stats">
        <div class="auth-hero-stat"><div class="l">Explora</div><div class="v">Mapas</div><div class="stat-visual-dots" aria-hidden="true">@for($i = 0; $i < 8; $i++)<span></span>@endfor</div></div>
        <div class="auth-hero-stat"><div class="l">Consulta</div><div class="v">Historial</div><svg width="60" height="12" viewBox="0 0 60 12" fill="none" aria-hidden="true"><path d="M2 8C10 8 12 2 20 6C28 10 32 2 40 7C48 12 50 2 58 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
        <div class="auth-hero-stat"><div class="l">Analiza</div><div class="v">Territorio</div><div class="stat-visual-clouds" aria-hidden="true">@for($i = 0; $i < 5; $i++)<x-publico.enviro-icono nombre="cloud" />@endfor</div></div>
    </div>
</aside>
