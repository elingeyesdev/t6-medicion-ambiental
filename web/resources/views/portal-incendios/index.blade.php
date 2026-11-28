<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avisos de incendio · GeoSur</title>
    <link rel="icon" href="{{ asset('img/marca/geosur-simbolo.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/envirosense/fuentes.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/mapas/centrar-seleccion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal-incendios.css') }}?v=trayectoria-4">
</head>
<body>
    <a href="#contenido-portal" class="salto-contenido">Saltar al contenido</a>
    <header class="barra">
        <div class="marco barra-interior">
            <a class="marca" href="{{ route('portal-incendios.index') }}"><img src="{{ asset('img/marca/geosur-simbolo.svg') }}" alt="" width="35" height="35"> GeoSur <span>Avisos de incendio</span></a>
            <div class="acceso-portal">
                @unless($brigadista)
                    <a class="boton boton-claro" href="{{ route('portal-incendios.brigada', $aviso ? ['aviso' => $aviso->id] : []) }}">Acceso de brigada</a>
                @else
                    <span class="identidad-portal"><strong>Brigada de incendios</strong><small>{{ $cuentaBrigada->nombre }}</small></span>
                    <form action="{{ route('portal-incendios.brigada.salir') }}" method="post">
                        @csrf
                        <button class="boton boton-claro" type="submit">Cerrar sesión</button>
                    </form>
                @endunless
            </div>
        </div>
    </header>
    <main class="marco" id="contenido-portal">
        @if(!$aviso && !$historial && !$brigadista && $avisos->onFirstPage())
            <section class="portada" aria-labelledby="titulo-portal">
                <div class="portada-texto">
                    <p class="portada-region"><span aria-hidden="true"></span> Santa Cruz, Bolivia</p>
                    <h1 id="titulo-portal">Cuidemos el lugar <em>que llamamos hogar</em></h1>
                    <p class="portada-intro">Pensamos en nuestra gente cuando algo pasa cerca. Si te preocupa tu comunidad, tu finca o el lugar donde vive tu familia, aquí puedes ver los avisos y saber qué se encontró.</p>
                    <div class="portada-acciones"><a class="boton boton-portada" href="#avisos">Ver avisos en el mapa</a><a class="portada-resueltos" href="{{ route('portal-incendios.index', ['vista' => 'resueltos']) }}#lista-avisos">Ver qué encontraron las brigadas</a></div>
                </div>
                <figure class="portada-paisaje">
                    <img src="{{ asset('img/portal-incendios/serrania-santiago.webp') }}" alt="Acantilados de la Serranía de Santiago sobre el bosque de la Chiquitanía" width="1280" height="720" fetchpriority="high">
                    <figcaption>Serranía de Santiago <span>· <a href="https://commons.wikimedia.org/wiki/File:Acantilado_en_la_Serran%C3%ADa_de_Santiago_-_Santiago_de_Chiquitos,_Bolivia.jpg" rel="noopener noreferrer" target="_blank">Ruditaly</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener noreferrer" target="_blank">CC BY-SA 4.0</a></span></figcaption>
                </figure>
            </section>
            <section class="guia-portal" aria-labelledby="titulo-guia">
                <div>
                    <h2 id="titulo-guia">Saber qué pasó también importa</h2>
                    <p>Si ves un aviso en un lugar que conoces, ábrelo para saber si una brigada ya se hizo cargo. Puedes volver más tarde para leer qué encontró.</p>
                </div>
            </section>
        @else
            @if($aviso || $historial)
                <div class="cabecera-avisos cabecera-interior"><div><p>Avisos de incendio</p><h1>{{ $aviso ? $aviso->municipio : ($resueltos ? 'Casos resueltos' : 'Historial de avisos') }}</h1></div></div>
            @endif
        @endif
        @if(session('estado'))<p class="mensaje" role="status">{{ session('estado') }}</p>@endif
        @if(!$aviso && !$historial)
            <div class="cabecera-avisos" id="avisos" tabindex="-1">
                <div>
                    <p>Santa Cruz, Bolivia</p>
                    @if($brigadista || !$avisos->onFirstPage())<h1>{{ $brigadista ? 'Panel de atención' : 'Qué pasa cerca' }}</h1>
                    @else<h2>Qué pasa cerca</h2>@endif
                </div>
                <dl class="pulso">
                    <div><dt>Por verificar</dt><dd>{{ $resumen['pendientes'] }}</dd></div>
                    <div><dt>En verificación</dt><dd>{{ $resumen['en_verificacion'] }}</dd></div>
                </dl>
            </div>
        @endif
        <div class="columnas {{ $aviso || $brigadista ? 'columnas-atencion' : '' }} {{ $aviso && !$brigadista ? 'columnas-ficha-publica' : '' }}">
            <section class="panel mapa-panel" aria-labelledby="titulo-mapa">
                <div class="panel-cabecera"><h2 id="titulo-mapa">Mapa de avisos</h2><span>Santa Cruz · Bolivia</span></div>
                <div id="portal-mapa" aria-label="Mapa de avisos publicados"></div>
                <div class="pie-mapa">
                    <span><i class="punto-estado" aria-hidden="true"></i> Por verificar</span>
                    <span><i class="punto-estado punto-verificacion" aria-hidden="true"></i> En verificación</span>
                    @if($historial || $aviso)<span><i class="punto-estado punto-archivo" aria-hidden="true"></i> Archivado</span>@endif
                </div>
            </section>
            <section class="panel" @unless($aviso) id="lista-avisos" tabindex="-1" @endunless aria-label="{{ $aviso ? 'Detalle del aviso' : 'Listado de avisos' }}">
                @if($aviso)
                    <div class="ficha">
                        <a href="{{ route('portal-incendios.index', $historial ? ['vista' => $filtroHistorial] : []) }}#lista-avisos">Volver a los avisos</a>
                        <h2>{{ $brigadista ? 'Ficha del aviso' : 'Sobre este aviso' }}</h2>
                        @if(str_starts_with($aviso->producto, 'SIMULACION'))<p class="nota-operativa">Simulacro · ejercicio de verificación</p>@endif
                        <span class="estado" data-estado="{{ $aviso->estado() }}">{{ $aviso->estado() }}</span>
                        @php($estadoAviso = $aviso->estado())
                        @unless($brigadista)
                            @include('portal-incendios.trayectoria')
                        @endunless
                        @if($brigadista && in_array($estadoAviso, ['Por verificar', 'En verificación'], true))
                            <section class="atencion-aviso" id="atencion" aria-labelledby="atencion-titulo">
                                <h3 id="atencion-titulo">Atención del aviso</h3>
                                @error('resultado')<p role="alert">{{ $message }}</p>@enderror
                                @if($estadoAviso === 'Por verificar')
                                    <p>Toma el aviso para asignarlo a tu cuenta y comenzar la verificación.</p>
                                    <form method="post" action="{{ route('portal-incendios.tomar', $aviso) }}">
                                        @csrf
                                        <button class="boton boton-principal" type="submit">Tomar aviso</button>
                                    </form>
                                @elseif((int) $aviso->portal_brigadista_id === (int) $cuentaBrigada?->id)
                                    <p>Este aviso está a cargo de tu cuenta.</p>
                                    <form method="post" action="{{ route('portal-incendios.resolver', $aviso) }}">
                                        @csrf
                                        <label for="resultado">Resultado de la verificación</label>
                                        <select required name="resultado" id="resultado">
                                            <option value="">Elige un resultado</option>
                                            @foreach(\App\Models\AvisoIncendio::RESULTADOS as $codigo => $nombre)
                                                <option value="{{ $codigo }}">{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        <label for="comentario-verificacion">Comentario de la brigada (opcional)</label>
                                        <textarea id="comentario-verificacion" name="comentario_verificacion" rows="3" maxlength="1000">{{ old('comentario_verificacion') }}</textarea>
                                        @error('comentario_verificacion')<p role="alert">{{ $message }}</p>@enderror
                                        <label class="nota-publica"><input type="checkbox" name="comentario_publico" value="1" @checked(old('comentario_publico'))> Mostrar este comentario en la ficha pública</label>
                                        @error('comentario_publico')<p role="alert">{{ $message }}</p>@enderror
                                        <button class="boton boton-principal" type="submit">Guardar resultado</button>
                                    </form>
                                @else
                                    <p>Una brigada ya se hizo cargo del aviso.</p>
                                @endif
                            </section>
                        @elseif($cuentaBrigada && (int) $aviso->portal_brigadista_id === (int) $cuentaBrigada->id && filled($aviso->comentario_verificacion) && !$aviso->comentario_publico)
                            <section class="atencion-aviso cerrado" aria-label="Comentario privado">
                                <h3>Tu comentario de verificación</h3>
                                <p>{{ $aviso->comentario_verificacion }}</p>
                            </section>
                        @endif
                        @if($brigadista)
                            @include('portal-incendios.trayectoria')
                        @elseif($estadoAviso === 'Por verificar')
                            <p class="trayectoria-acceso"><a href="{{ route('portal-incendios.brigada', ['aviso' => $aviso->id]) }}">Ingresar como brigada</a></p>
                        @endif
                        <dl>
                            <div><dt>Ubicación aproximada</dt><dd>{{ $aviso->latitud }}, {{ $aviso->longitud }}<br><a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($aviso->latitud . ',' . $aviso->longitud) }}" target="_blank" rel="noopener noreferrer">Abrir en Google Maps</a></dd></div>
                        </dl>
                        <details class="datos-origen">
                            <summary>De dónde viene el aviso</summary>
                            <dl>
                                <div><dt>Fuente</dt><dd>{{ str_starts_with($aviso->producto, 'SIMULACION') ? 'Ejercicio local · ' : 'NASA FIRMS · ' }}{{ $aviso->producto }}</dd></div>
                                <div><dt>Detecciones agrupadas</dt><dd>{{ $aviso->detecciones }}{{ str_starts_with($aviso->producto, 'SIMULACION') ? '' : ' · confianza alta' }}</dd></div>
                            </dl>
                        </details>
                    </div>
                @else
                    <div class="panel-cabecera"><h2>{{ $resueltos ? 'Casos resueltos' : ($historial ? 'Historial' : 'Avisos activos') }}</h2></div>
                    <nav class="pestanas" aria-label="Filtrar avisos">
                        <a href="{{ route('portal-incendios.index') }}#lista-avisos" @unless($historial) aria-current="page" @endunless>Activos</a>
                        <a href="{{ route('portal-incendios.index', ['vista' => 'historial']) }}#lista-avisos" @if($historial && !$resueltos) aria-current="page" @endif>Historial</a>
                        <a href="{{ route('portal-incendios.index', ['vista' => 'resueltos']) }}#lista-avisos" @if($resueltos) aria-current="page" @endif>Resueltos</a>
                    </nav>
                    <ul class="lista">
                        @forelse($avisos as $item)
                            @php($estadoFila = $item->estado())
                            <li class="fila-aviso">
                                <a class="detalle-enlace" href="{{ route('portal-incendios.ficha', ['aviso' => $item] + ($historial ? ['vista' => $filtroHistorial] : [])) }}">
                                    <strong>{{ $item->municipio }}</strong><span class="estado" data-estado="{{ $estadoFila }}">{{ $estadoFila }}</span>
                                    <small>{{ $item->observado_en->setTimezone('America/La_Paz')->format('d/m/Y · H:i') }}@if($brigadista) · {{ $item->detecciones }} {{ $item->detecciones === 1 ? 'detección' : 'detecciones' }}@endif</small>
                                </a>
                                @if($brigadista && $estadoFila === 'Por verificar')
                                    <form class="accion-fila" method="post" action="{{ route('portal-incendios.tomar', $item) }}">
                                        @csrf
                                        <button type="submit" class="boton boton-tomar">Tomar aviso<span class="solo-lector-portal"> en {{ $item->municipio }} del {{ $item->observado_en->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</span></button>
                                    </form>
                                @elseif($brigadista && $estadoFila === 'En verificación' && (int) $item->portal_brigadista_id === (int) $cuentaBrigada?->id)
                                    <div class="accion-fila"><a class="boton boton-tomar" href="{{ route('portal-incendios.ficha', $item) }}#atencion">Registrar resultado</a></div>
                                @endif
                            </li>
                        @empty
                            <li class="vacio">{{ $resueltos ? 'Cuando una brigada comparta lo que encontró, podrás verlo aquí.' : ($historial ? 'Todavía no hay avisos archivados.' : 'Por ahora no hay avisos activos en el mapa.') }}</li>
                        @endforelse
                    </ul>
                    @if($avisos->hasPages())
                        <nav class="paginacion" aria-label="Páginas de avisos">
                            @if($avisos->previousPageUrl())<a href="{{ $avisos->previousPageUrl() }}#lista-avisos">Anterior</a>@endif
                            <span>{{ $avisos->currentPage() }} de {{ $avisos->lastPage() }}</span>
                            @if($avisos->nextPageUrl())<a href="{{ $avisos->nextPageUrl() }}#lista-avisos">Siguiente</a>@endif
                        </nav>
                    @endif
                @endif
            </section>
        </div>
        <footer><a href="https://firms.modaps.eosdis.nasa.gov/" target="_blank" rel="noopener noreferrer">NASA FIRMS</a> · Los puntos del mapa muestran ubicaciones aproximadas. Una señal de calor necesita revisarse en el lugar para saber si hay un incendio.</footer>
    </main>
    <script type="application/json" id="configuracion-mapa">@json(\App\Support\ConfiguracionMapa::paraWeb())</script>
    <script type="application/json" id="datos-portal-mapa">
        @json($mapaAvisos)
    </script>
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/mapas/centrar-seleccion.js') }}?v=2"></script>
    <script type="module" src="{{ asset('js/portal-incendios.js') }}?v=trayectoria-3"></script>
</body>
</html>
