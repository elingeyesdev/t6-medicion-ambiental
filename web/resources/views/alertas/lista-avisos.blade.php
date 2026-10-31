@forelse($avisos as $aviso)
@php($a = $aviso->alerta)
<section class="card alerta-tarjeta" data-aviso="{{ $aviso->id }}"><div class="card-body"><h2>{{ $a->contenido['nombre'] }} · {{ $a->contenido['umbral']['nombre'] }}</h2>
    <p>{{ $metricas[$a->metrica]['nombre'] }}: {{ $a->contenido['valor'] }} {{ $metricas[$a->metrica]['unidad'] }} · {{ \App\Services\Datos\CatalogoAmbiental::ORIGENES[$a->origen] }}</p>
    <p>{{ $a->instante->setTimezone('America/La_Paz')->format('d/m/Y H:i') }} · Hora de Bolivia</p>
    @foreach($envios[$aviso->id] ?? [] as $envio)
    <p>{{ \App\Services\Notificaciones\CanalesAvisos::NOMBRES[$envio->canal] }}: {{ $envio->etiqueta() }}</p>
    @endforeach
    <div class="alertas-acciones"><a class="btn btn-outline-primary" href="{{ route('alertas', ['metrica' => $a->metrica, 'origen' => $a->origen]) }}">Ver alertas</a>
    @unless($aviso->leido_el)<form method="post" action="{{ route('avisos.leer', $aviso->id) }}">@csrf @method('PATCH')<button class="btn btn-primary" type="submit">Marcar como leído</button></form>@else<span>Leído</span>@endunless</div>
</div></section>
@empty<p class="card card-body mt-4">Aún no tienes avisos. Elige las variables que te interesan en Configurar avisos.</p>@endforelse
