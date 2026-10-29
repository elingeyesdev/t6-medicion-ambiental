<?php

namespace App\Http\Requests\Mapas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PoiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mapa.ver') && $this->user()?->can('datos.consultar');
    }

    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[;\x00-\x1f]|%3b/iu'],
            'pais' => ['required', 'string', Rule::in(array_keys(config('capas.poi_paises')))],
        ];
    }

    public function messages(): array
    {
        return ['texto.*' => 'Escribe un establecimiento de 2 a 120 caracteres.', 'pais.*' => 'Elige un país disponible.'];
    }
}
