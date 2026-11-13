<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PortalRole;
use App\Models\User;
use App\Services\UserRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'portal:create-admin {email} {--name=} {--role=administrador : administrador o jefe_zona}';

    protected $description = 'Crea una cuenta activa con permiso de aprobar solicitudes (sin pasar por aprobación)';

    public function handle(UserRegistrationService $registration): int
    {
        $role = PortalRole::tryFrom((string) $this->option('role'));

        if ($role === null || $role === PortalRole::Usuario) {
            $this->error('El rol debe ser administrador o jefe_zona.');

            return self::FAILURE;
        }

        $email = mb_strtolower((string) $this->argument('email'));
        $name = (string) ($this->option('name') ?: $this->ask('Nombre'));

        // La contraseña se pide oculta: nunca por argumento (queda en el historial del shell).
        $password = (string) $this->secret('Contraseña');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255', 'unique:'.User::class],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $registration->createActive($name, $email, $password, $role);

        $this->info("Cuenta creada: {$user->email} ({$role->value}).");

        return self::SUCCESS;
    }
}
