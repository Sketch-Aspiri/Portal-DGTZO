<?php

declare(strict_types=1);

namespace App\Services\Tutoriales;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Collection;

/** Qué guías existen y quién puede ver cada una. */
final class CatalogoGuias
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /** @return Collection<string, Guia> */
    public function todas(): Collection
    {
        return collect(config('guias.guias', []))->map(fn (array $datos, string $slug): Guia => Guia::desde($slug, $datos));
    }

    public function buscar(string $slug): ?Guia
    {
        return $this->todas()->get($slug);
    }

    /** @return Collection<string, Guia> */
    public function visiblesPara(?User $user): Collection
    {
        $completa = $this->sesionCompleta($user);
        $accesos = $completa ? $user->subsistemasAccesibles() : [];

        return $this->todas()->filter(fn (Guia $guia): bool => $guia->publica
            || ($completa && $this->leCorresponde($guia, $user, $accesos)));
    }

    /**
     * Mismas condiciones que los middleware account.active y two-factor: cuenta activa y, si usa o debe usar la
     * verificación en dos pasos, ya superada en esta sesión.
     */
    public function sesionCompleta(?User $user): bool
    {
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->twoFactor->isVerifiedInSession($user);
        }

        return ! $user->requiresTwoFactor();
    }

    /** @param array<string, array<string, mixed>> $accesos */
    private function leCorresponde(Guia $guia, User $user, array $accesos): bool
    {
        // Quien administra el portal las ve todas: da soporte a los demás.
        if ($user->canApprove()) {
            return true;
        }

        if ($guia->sistema === 'portal') {
            return $guia->roles === null || in_array($user->portal_role->value, $guia->roles, true);
        }

        $acceso = $accesos[$guia->sistema] ?? null;

        return $acceso !== null && ($guia->roles === null || in_array($acceso['rol_asignado'], $guia->roles, true));
    }
}
