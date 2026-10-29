<?php

namespace App\Http\Requests\Cuentas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = ['unidades' => ['required', 'array:temperatura,viento', 'min:1'],
            'user_id' => ['prohibited'], 'rol' => ['prohibited'], 'activo' => ['prohibited']];
        foreach (config('unidades') as $clave => $grupo) {
            $reglas['unidades.'.$clave] = ['sometimes', 'string', Rule::in(array_keys($grupo['opciones']))];
        }

        return $reglas;
    }

    public function messages(): array
    {
        return ['unidades.*.in' => 'Elige una unidad de la lista.', 'unidades.array' => 'Elige unidades de temperatura o viento.'];
    }
}
