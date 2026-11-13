<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;

/**
 * Lado "origen" del inicio de sesión único: el Dashboard y Tickets le preguntan al portal si hay una sesión
 * completa en este navegador. Solo se comparte una sesión completa (cuenta activa, con 2FA superado cuando lo
 * tiene o es obligatorio) y solo hacia subsistemas a los que la persona tiene acceso.
 */
final class SingleSignOnService
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function enabled(): bool
    {
        return (new EcosystemSignature)->configured();
    }

    public function url(string $system, string $path): string
    {
        return config('ecosistema.urls.'.$system).$path;
    }

    /**
     * @return bool|null true: sesión completa y 2FA superado; false: sesión completa sin 2FA (no obligatorio);
     *                   null: no hay sesión completa que compartir
     */
    public function completeSession(Request $request): ?bool
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isActive()) {
            return null;
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->twoFactor->isVerifiedInSession($user) ? true : null;
        }

        return $user->requiresTwoFactor() ? null : false;
    }

    public function hasAccessTo(User $user, string $subsystem): bool
    {
        return $user->accesos()->where('subsistema', $subsystem)->exists();
    }
}
