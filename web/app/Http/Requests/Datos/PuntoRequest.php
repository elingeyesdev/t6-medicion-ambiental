<?php

namespace App\Http\Requests\Datos;

use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(CatalogoAmbiental $catalogo): array
    {
        return ['nombre' => ['required', 'string', 'max:100'], 'codigo' => ['sometimes', 'required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9_-]*$/D', 'unique:puntos_ambientales,codigo'],
            'pais' => ['required', Rule::in(array_column($catalogo->paises(), 'codigo'))],
            'latitud' => ['required', 'numeric', 'between:-90,90'], 'longitud' => ['required', 'numeric', 'between:-180,180'],
            'tipo' => ['sometimes', Rule::in(['manual', 'iot'])], 'activo' => ['prohibited']];
    }
}
