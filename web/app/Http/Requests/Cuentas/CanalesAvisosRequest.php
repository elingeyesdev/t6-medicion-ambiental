<?php

namespace App\Http\Requests\Cuentas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CanalesAvisosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'notif_email' => ['required', 'boolean'], 'notif_telegram' => ['required', 'boolean'], 'notif_whatsapp' => ['required', 'boolean'],
            'telegram_destino' => ['nullable', 'string', 'max:20', 'regex:/^[1-9]\d{3,19}$/D'],
            'whatsapp_destino' => ['nullable', 'string', 'max:16', 'regex:/^\+?[1-9]\d{7,14}$/D'],
            'consentimiento' => [Rule::when(fn () => $this->boolean('notif_email') || $this->boolean('notif_telegram') || $this->boolean('notif_whatsapp'),
                ['required', 'accepted'], ['nullable', 'boolean'])],
            'rol' => ['prohibited'], 'activo' => ['prohibited'], 'email' => ['prohibited'],
            'usuario_id' => ['prohibited'], 'user_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['consentimiento.required' => 'Confirma que deseas recibir los avisos.', 'consentimiento.accepted' => 'Confirma que deseas recibir los avisos.',
            'telegram_destino.regex' => 'Escribe tu ID de chat privado de Telegram.', 'whatsapp_destino.regex' => 'Escribe tu número con código de país.'];
    }
}
