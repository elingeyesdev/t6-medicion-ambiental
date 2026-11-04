<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => Str::lower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['bail', 'required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(12), function ($attribute, $value, $fail): void {
                // bcrypt limita por bytes, no por cantidad de caracteres Unicode.
                if (strlen($value) > 72) {
                    $fail('La contraseña no puede superar 72 bytes.');
                }
            }],
        ];

        if (! $this->is('api/*')) {
            $reglas += [
                'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
                'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
                'notif_email' => ['sometimes', 'boolean'], 'notif_telegram' => ['sometimes', 'boolean'], 'notif_whatsapp' => ['sometimes', 'boolean'],
                'telegram_destino' => ['nullable', 'string', 'max:20', 'regex:/^[1-9]\d{3,19}$/D'],
                'whatsapp_destino' => ['nullable', 'string', 'max:16', 'regex:/^\+?[1-9]\d{7,14}$/D'],
                'consentimiento' => ['sometimes', 'accepted'],
            ];
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe tu nombre.', 'name.max' => 'Usa hasta 100 caracteres.',
            'email.required' => 'Escribe tu correo.', 'email.email' => 'Revisa el correo.',
            'email.unique' => 'Ese correo ya tiene una cuenta.',
            'password.required' => 'Escribe una contraseña.', 'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'Usa al menos 12 caracteres.',
        ];
    }
}
