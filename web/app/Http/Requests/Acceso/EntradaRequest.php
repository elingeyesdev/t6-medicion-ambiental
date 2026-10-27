<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class EntradaRequest extends FormRequest
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
        return ['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:4096']];
    }

    public function messages(): array
    {
        return ['email.required' => 'Escribe tu correo.', 'email.email' => 'Revisa el correo.', 'password.required' => 'Escribe tu contraseña.'];
    }
}
