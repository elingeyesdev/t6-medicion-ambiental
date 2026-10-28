<?php

namespace App\Http\Requests\Cuentas;

use Illuminate\Foundation\Http\FormRequest;

class NombreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'rol' => ['prohibited'], 'activo' => ['prohibited'], 'email' => ['prohibited']];
    }
}
