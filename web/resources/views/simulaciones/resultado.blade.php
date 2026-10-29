<section class="card sim-resultado">
    <div class="card-header"><h2>{{ $sesion->nombre }}</h2><span class="badge bg-orange-lt">Simulado</span></div>
    <div class="card-body">
        <p>{{ $metricas[$sesion->configuracion['metrica']]['nombre'] }} · {{ $sesion->configuracion['dias'] }} días · Lecturas cada {{ $sesion->configuracion['intervalo'] }} minutos.</p>
        <div class="sim-reproductor"><button class="btn btn-primary" type="button" data-accion="iniciar">Reproducir</button><button class="btn btn-outline-primary" type="button" data-accion="pausar">Pausar</button><button class="btn btn-outline-primary" type="button" data-accion="detener">Detener</button><button class="btn btn-outline-primary" type="button" data-accion="reiniciar">Reiniciar</button><span id="sim-estado" role="status">{{ $sesion->estado }}</span></div>
        <label class="form-label mt-3" for="sim-tiempo">Recorrido de la simulación · <span id="sim-fecha"></span></label><input id="sim-tiempo" type="range" class="form-range" min="0" max="{{ count($sesion->resultado['instantes'])-1 }}" value="{{ $sesion->posicion }}">
        <div class="sim-vista"><div id="sim-mapa" role="region" aria-label="Zonas simuladas"></div><div><h3>Comparación</h3><div id="sim-valores"></div><svg id="sim-grafico" viewBox="0 0 640 220" role="img" aria-label="Evolución de los valores simulados por zona"></svg><p id="sim-rango"></p></div></div>
        <div class="table-responsive mt-3"><table class="table"><thead><tr><th>Zona</th><th>Escenario</th><th>Mínimo</th><th>Máximo</th><th>Promedio</th></tr></thead><tbody>@foreach($sesion->resultado['series'] as $s)<tr><th>{{ $s['nombre'] }}</th><td>{{ $s['escenario'] }} · {{ $s['nivel'] }}</td><td>{{ min($s['valores']) }}</td><td>{{ max($s['valores']) }}</td><td>{{ round(array_sum($s['valores'])/count($s['valores']),2) }}</td></tr>@endforeach</tbody></table></div>
        <form action="{{ route('simulaciones.publicar',$sesion->id) }}" method="post">@csrf<button class="btn btn-outline-primary" type="submit">Guardar lecturas pasadas en el historial</button></form>
        <p class="mt-3">Siempre se guardan como simuladas. Las fechas futuras permanecen dentro de esta simulación.</p>
        <noscript><p>Activa JavaScript para reproducir el escenario y recorrer el mapa. El resumen y la simulación guardada se conservan.</p></noscript>
    </div>
</section>
