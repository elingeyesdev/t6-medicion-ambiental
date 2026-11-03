@if(\Illuminate\Support\Facades\Route::has('avisos.canales'))
<details class="card mt-3"><summary class="card-header"><h2>Canales de avisos</h2></summary><div class="card-body">
    <p>Elige dónde recibir tus nuevos avisos. Guarda primero tu ubicación y activa las variables en Configurar avisos.</p>
    <form method="post" action="{{ route('avisos.canales') }}">@csrf @method('PUT')
        @foreach(\App\Services\Notificaciones\CanalesAvisos::CUENTA as $canal)
        @php($nombre = \App\Services\Notificaciones\CanalesAvisos::NOMBRES[$canal])
        <fieldset class="mb-4"><legend class="form-label">{{ $nombre }}</legend>
            <input type="hidden" name="notif_{{ $canal }}" value="0">
            @if($canales->disponible($canal))
            <label class="form-check"><input class="form-check-input" type="checkbox" name="notif_{{ $canal }}" value="1" @checked(old('notif_'.$canal, $preferenciasCuenta['notif_'.$canal]))> Recibir por {{ $nombre }}</label>
            @if($canal === 'email')
                <p class="mt-2">{{ auth()->user()->email }}</p>
                @if(config('avisos.email.mailer') === 'log')<p>Este correo se guardará solo en el registro local. No llegará a tu buzón.</p>@endif
            @else
                <label for="destino-{{ $canal }}" class="form-label mt-2">{{ $canal === 'telegram' ? 'Tu ID de chat privado' : 'Tu número con código de país' }}</label>
                <input id="destino-{{ $canal }}" name="{{ $canal }}_destino" class="form-control" value="{{ old($canal.'_destino', $preferenciasCuenta[$canal.'_destino']) }}" maxlength="{{ $canal === 'telegram' ? 20 : 16 }}" inputmode="tel">
            @endif
            @else
            <p>{{ $nombre }} no está disponible. Puedes desactivar los avisos que tenías elegidos al guardar.</p>
            @endif
        </fieldset>
        @endforeach
        <label class="form-check mb-4"><input type="checkbox" class="form-check-input" name="consentimiento" value="1"> Confirmo que estos destinos son míos y quiero recibir avisos. Puedo desactivarlos aquí.</label>
        <button class="btn btn-primary" type="submit">Guardar canales</button>
    </form>
</div></details>
@endif
