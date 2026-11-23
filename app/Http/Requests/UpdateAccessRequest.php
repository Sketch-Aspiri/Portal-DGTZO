<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Cambiar el rol y la ubicacion de UN acceso ya vigente. Misma validacion que la aprobacion (catalogo vivo, roles
 * amplios solo para el administrador); solo se acepta el sistema de la ruta.
 */
class UpdateAccessRequest extends ApproveUserRequest
{
    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $route = (string) $this->route('subsistema');

                foreach ((array) $this->input('accesos', []) as $key => $data) {
                    if ($key !== $route && filter_var($data['activo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add('accesos', __('portal.invalid_subsystem'));
                    }
                }
            },
        ];
    }
}
