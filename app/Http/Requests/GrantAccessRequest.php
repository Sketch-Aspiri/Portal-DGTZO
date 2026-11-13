<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Dar acceso a otro subsistema a una cuenta ya activa. Misma validación que la aprobación (rol, ubicación del
 * catálogo vivo, roles amplios solo para el administrador) más: el subsistema no debe estar ya asignado.
 */
class GrantAccessRequest extends ApproveUserRequest
{
    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $target = $this->route('user');

                if (! $target instanceof User) {
                    return;
                }

                $existing = $target->accesos()->pluck('subsistema')->all();

                foreach ((array) $this->input('accesos', []) as $key => $data) {
                    if (is_string($key) && in_array($key, $existing, true)
                        && filter_var($data['activo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add("accesos.$key.activo", __('portal.already_has_access'));
                    }
                }
            },
        ];
    }
}
