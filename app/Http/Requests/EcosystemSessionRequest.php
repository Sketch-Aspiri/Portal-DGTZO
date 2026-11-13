<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Subsistemas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parámetros de las rutas GET /ecosistema/*: llegan por redirección, así que se validan estrictamente. */
class EcosystemSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'para' => ['sometimes', 'string', Rule::in(Subsistemas::claves())],
            'estado' => ['sometimes', 'nullable', 'string', 'size:40', 'regex:/^[A-Za-z0-9]+$/D'],
            'codigo' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^[A-Za-z0-9_\-.]+$/D'],
        ];
    }
}
