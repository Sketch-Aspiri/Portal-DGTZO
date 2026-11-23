<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PortalRole;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Alta de una cuenta por un administrador o jefe de zona: datos de la persona, su rol en el portal y sus accesos
 * (misma validacion que la aprobacion).
 */
class CreateUserRequest extends ApproveUserRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[\pL\pM\s\.\'\-]+$/u'],
            'email' => [
                'required', 'string', 'lowercase', 'email:rfc', 'max:255',
                'not_regex:/\.invalid$/i', 'unique:'.User::class,
            ],
            'portal_role' => ['nullable', Rule::enum(PortalRole::class)],
        ];
    }

    public function portalRole(): PortalRole
    {
        return PortalRole::tryFrom((string) $this->input('portal_role')) ?? PortalRole::Usuario;
    }
}
