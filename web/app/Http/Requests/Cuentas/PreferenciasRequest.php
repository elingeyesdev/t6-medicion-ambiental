<?php

namespace App\Http\Requests\Cuentas;

use Illuminate\Foundation\Http\FormRequest;

class PreferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'notif_email' => ['sometimes', 'boolean'], 'notif_whatsapp' => ['sometimes', 'boolean'], 'notif_telegram' => ['sometimes', 'boolean'],
            'whatsapp_destino' => ['nullable', 'string', 'max:50'], 'telegram_destino' => ['nullable', 'string', 'max:50'],
            'rol' => ['prohibited'], 'activo' => ['prohibited'], 'user_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['latitud.required_with' => 'Marca una ubicación completa en el mapa.', 'longitud.required_with' => 'Marca una ubicación completa en el mapa.',
            'latitud.between' => 'Revisa la ubicación elegida.', 'longitud.between' => 'Revisa la ubicación elegida.'];
    }
}
