<?php

namespace Database\Factories;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Clave-Segura-2026!'),
            'remember_token' => Str::random(10),
            'status' => UserStatus::Pending,
            'portal_role' => PortalRole::Usuario,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => UserStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => UserStatus::Inactive]);
    }

    /** 2FA configurado (quienes aprueban lo necesitan para entrar a cualquier pantalla). */
    public function withTwoFactor(): static
    {
        return $this->state([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
    }

    public function administrador(): static
    {
        return $this->active()->withTwoFactor()->state(['portal_role' => PortalRole::Administrador]);
    }

    public function jefeZona(): static
    {
        return $this->active()->withTwoFactor()->state(['portal_role' => PortalRole::JefeZona]);
    }
}
