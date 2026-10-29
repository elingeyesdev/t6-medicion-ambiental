<?php

namespace App\Http\Requests\Cuentas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:256'],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(12), function ($attribute, $value, $fail): void {
                if (strlen($value) > 72) {
                    $fail('La contraseña no puede superar 72 bytes.');
                }
            }],
            'rol' => ['prohibited'], 'activo' => ['prohibited'], 'usuario_id' => ['prohibited'], 'user_id' => ['prohibited'], 'email' => ['prohibited']];
    }
}
