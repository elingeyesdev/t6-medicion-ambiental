@if(\Illuminate\Support\Facades\Route::has('avisos.push'))
<section class="card mt-3"><div class="card-body"><h2>Avisos del navegador</h2>
    <p>Recibe tus nuevos avisos en este navegador. Al activarlo, reemplazas el navegador que elegiste antes para tu cuenta.</p>
    <form id="avisos-push" data-worker="{{ route('avisos.servicio-push') }}" action="{{ route('avisos.push') }}">@csrf
        @if($canales->disponible('push'))
        <label class="form-check my-3"><input id="push-consentimiento" type="checkbox" class="form-check-input"> Quiero recibir mis avisos en este navegador.</label>
        <div class="alertas-acciones"><button class="btn btn-primary" type="submit">Activar en este navegador</button></div>
        @else<p>Los avisos del navegador no están disponibles. Puedes consultar tus avisos aquí.</p>
        @endif
        <button class="btn btn-outline-primary mt-3" id="push-desactivar" type="button">Desactivar avisos del navegador</button>
        <p id="push-estado" class="mt-3" role="status" aria-live="polite"></p>
    </form>
    <script id="avisos-push-publico" type="application/json">@json(['firebase' => $canales->configuracionPushPublica(), 'vapid' => config('avisos.push.vapid_publica')])</script>
</div></section>
@endif
