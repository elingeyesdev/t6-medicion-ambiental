<?php

namespace App\Http\Requests\Cuentas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PushAvisosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean'], 'token' => ['nullable', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:\-]+$/D', Rule::requiredIf(fn () => $this->boolean('activo'))],
            'consentimiento' => [Rule::when(fn () => $this->boolean('activo'), ['required', 'accepted'], ['nullable', 'boolean'])],
            'usuario_id' => ['prohibited'], 'user_id' => ['prohibited'], 'rol' => ['prohibited']];
    }
}
