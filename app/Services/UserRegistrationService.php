<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\NewUserPendingApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class UserRegistrationService
{
    /**
     * Crea la cuenta pendiente, sin rol ni accesos, y avisa a quienes aprueban.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->forceFill(['status' => UserStatus::Pending, 'portal_role' => PortalRole::Usuario])->save();

            activity('auth')->performedOn($user)->event('registered')->log('registered');

            return $user;
        });

        Notification::send(User::query()->approvers()->get(), new NewUserPendingApproval($user));

        return $user;
    }

    /** Alta por consola (sin aprobación): para el primer administrador. */
    public function createActive(string $name, string $email, string $password, PortalRole $role): User
    {
        $user = new User(['name' => $name, 'email' => mb_strtolower($email), 'password' => $password]);
        $user->forceFill([
            'status' => UserStatus::Active,
            'portal_role' => $role,
            'decided_at' => now(),
        ])->save();

        return $user;
    }
}
