<?php

namespace App\Http\Requests\Datos;

use Illuminate\Foundation\Http\FormRequest;

class ImportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('datos.gestionar') ?? false;
    }

    public function rules(): array
    {
        return [
            'formato' => ['sometimes', 'required', 'in:csv,historial'],
            'saneado' => ['accepted_if:formato,historial'],
            'archivo' => ['required', 'file', 'max:2048', 'extensions:csv,txt,json'],
        ];
    }

    public function messages(): array
    {
        return ['saneado.accepted_if' => 'Confirma que revisaste la procedencia y retiraste los datos privados.',
            'archivo.extensions' => 'Elige un archivo CSV o JSON.'];
    }
}
