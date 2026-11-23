<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\ProvisionAccess;
use App\Jobs\SyncAccess;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use App\Support\Subsistemas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserApprovalService
{
    /**
     * Aprueba una cuenta pendiente y le da acceso a los subsistemas elegidos; la cuenta en cada subsistema se crea
     * después, en cola (ProvisionAccess), con reintentos.
     *
     * @param  array<string, array{rol: string, contexto: array<string, mixed>}>  $accesos  [subsistema => datos]
     */
    public function approve(User $user, User $actor, array $accesos): void
    {
        $created = DB::transaction(function () use ($user, $actor, $accesos): array {
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->assertDecidable($fresh, $actor);

            $ids = [];

            foreach ($accesos as $subsistema => $datos) {
                $access = $fresh->accesos()->updateOrCreate(
                    ['subsistema' => $subsistema],
                    ['rol' => $datos['rol'], 'contexto' => $datos['contexto']],
                );
                $access->forceFill([
                    'aprovisionamiento' => UsuarioSubsistema::PENDIENTE,
                    'ultimo_error' => null,
                    'intentos' => 0,
                ])->save();
                $ids[] = $access->id;
            }

            $fresh->solicitudAcceso?->forceFill(['estado' => SolicitudAcceso::APROBADA])->save();

            $fresh->forceFill([
                'status' => UserStatus::Active,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('approved')
                ->withProperties(['accesos' => array_map(fn (array $d): array => ['rol' => $d['rol'], 'contexto' => $d['contexto']], $accesos)])
                ->log('approved');

            return $ids;
        });

        foreach ($created as $id) {
            ProvisionAccess::dispatch($id);
        }
    }

    /**
     * Da acceso a otros subsistemas a una cuenta YA activa (la cuenta en cada uno se crea en cola, como al aprobar).
     * Solo agrega: cambiar el rol o quitar un acceso no se sincroniza con el subsistema, así que no se ofrece.
     * Nadie se da acceso a sí mismo: un administrador no amplía su propio alcance.
     *
     * @param  array<string, array{rol: string, contexto: array<string, mixed>}>  $accesos
     */
    public function grant(User $target, User $actor, array $accesos): void
    {
        $created = DB::transaction(function () use ($target, $actor, $accesos): array {
            $fresh = User::query()->lockForUpdate()->findOrFail($target->id);

            if (! $actor->canApprove()) {
                throw new BusinessRuleException(__('portal.cannot_approve'));
            }

            if ($fresh->is($actor)) {
                throw new BusinessRuleException(__('portal.cannot_self_grant'));
            }

            if ($fresh->status !== UserStatus::Active) {
                throw new BusinessRuleException(__('portal.not_active'));
            }

            $ids = [];

            foreach ($accesos as $subsistema => $datos) {
                $existing = $fresh->todosLosAccesos()->where('subsistema', $subsistema)->first();

                if ($existing !== null && ! $existing->isRevoked()) {
                    throw new BusinessRuleException(__('portal.already_has_access'));
                }

                if ($existing !== null) {
                    // Acceso quitado antes: la cuenta del subsistema sigue ahi (inactiva), asi que se reactiva con su nuevo rol.
                    $existing->forceFill([
                        'rol' => $datos['rol'], 'contexto' => $datos['contexto'], 'revocado_at' => null,
                        'aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0, 'ultimo_error' => null,
                        'accion_pendiente' => $existing->aprovisionado_at !== null ? UsuarioSubsistema::ACCION_ACTUALIZAR : null,
                    ])->save();
                    $access = $existing;
                } else {
                    $access = $fresh->todosLosAccesos()->create(['subsistema' => $subsistema, 'rol' => $datos['rol'], 'contexto' => $datos['contexto']]);
                    $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0])->save();
                }

                $ids[] = $access->id;
            }

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('access_granted')
                ->withProperties(['accesos' => array_map(fn (array $d): array => ['rol' => $d['rol'], 'contexto' => $d['contexto']], $accesos)])
                ->log('access_granted');

            return $ids;
        });

        $this->dispatchFor($created);
    }

    /**
     * Crea una cuenta ya activa (alta por un administrador o jefe de zona) con sus accesos. La persona define su
     * contrasena con el enlace que recibe por correo; las cuentas en los subsistemas se crean cuando la define (asi
     * entran alli con la misma contrasena), no antes.
     *
     * @param  array<string, array{rol: string, contexto: array<string, mixed>}>  $accesos
     */
    public function createAccount(User $actor, string $name, string $email, PortalRole $portalRole, array $accesos): User
    {
        if (! $actor->canApprove()) {
            throw new BusinessRuleException(__('portal.cannot_approve'));
        }

        // Los roles del portal con alcance amplio solo los otorga un administrador.
        if ($portalRole !== PortalRole::Usuario && $actor->portal_role !== PortalRole::Administrador) {
            throw new BusinessRuleException(__('portal.privileged_role'));
        }

        $user = DB::transaction(function () use ($actor, $name, $email, $portalRole, $accesos): User {
            $user = new User(['name' => trim($name), 'email' => mb_strtolower($email), 'password' => Str::random(40)]);
            $user->forceFill([
                'status' => UserStatus::Active,
                'portal_role' => $portalRole,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            foreach ($accesos as $subsistema => $datos) {
                $access = $user->todosLosAccesos()->create(['subsistema' => $subsistema, 'rol' => $datos['rol'], 'contexto' => $datos['contexto']]);
                $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0])->save();
            }

            activity('auth')->performedOn($user)->causedBy($actor)->event('created_by_admin')
                ->withProperties(['portal_role' => $portalRole->value, 'accesos' => array_map(fn (array $d): array => ['rol' => $d['rol'], 'contexto' => $d['contexto']], $accesos)])
                ->log('created_by_admin');

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    /** Envia (o reenvia) el enlace para definir la contrasena. */
    public function invite(User $target, User $actor): void
    {
        if (! $actor->canApprove()) {
            throw new BusinessRuleException(__('portal.cannot_approve'));
        }

        if ($target->status !== UserStatus::Active) {
            throw new BusinessRuleException(__('portal.not_active'));
        }

        Password::sendResetLink(['email' => $target->email]);

        activity('auth')->performedOn($target)->causedBy($actor)->event('password_link_sent')->log('password_link_sent');
    }

    /**
     * Cambia el rol y la ubicacion de un acceso vigente; el cambio llega al subsistema en cola.
     *
     * @param  array{rol: string, contexto: array<string, mixed>}  $datos
     */
    public function changeAccess(User $target, User $actor, string $subsistema, array $datos): void
    {
        $id = DB::transaction(function () use ($target, $actor, $subsistema, $datos): int {
            [$fresh, $access] = $this->lockedAccess($target, $actor, $subsistema);

            $before = ['rol' => $access->rol, 'contexto' => $access->contexto];
            $neverProvisioned = $access->aprovisionado_at === null && $access->aprovisionamiento !== UsuarioSubsistema::CONFLICTO;

            $access->forceFill([
                'rol' => $datos['rol'], 'contexto' => $datos['contexto'],
                'aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0, 'ultimo_error' => null,
                // Si la cuenta de alla aun no se creo, el cambio se incluye en el alta pendiente.
                'accion_pendiente' => $neverProvisioned ? null : UsuarioSubsistema::ACCION_ACTUALIZAR,
            ])->save();

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('access_changed')
                ->withProperties(['subsistema' => $subsistema, 'antes' => $before, 'despues' => $datos])->log('access_changed');

            return $access->id;
        });

        $this->dispatchFor([$id]);
    }

    /** Quita el acceso a un subsistema: la cuenta de alla se desactiva (no se borra) y deja de aparecer aqui. */
    public function revokeAccess(User $target, User $actor, string $subsistema): void
    {
        $id = DB::transaction(function () use ($target, $actor, $subsistema): int {
            [$fresh, $access] = $this->lockedAccess($target, $actor, $subsistema);

            $access->forceFill([
                'revocado_at' => now(), 'aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0, 'ultimo_error' => null,
                'accion_pendiente' => UsuarioSubsistema::ACCION_REVOCAR,
            ])->save();

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('access_revoked')
                ->withProperties(['subsistema' => $subsistema, 'rol' => $access->rol])->log('access_revoked');

            return $access->id;
        });

        $this->dispatchFor([$id]);
    }

    /** Da de baja la cuenta: pierde el portal y queda desactivada en cada sistema (los datos se conservan). */
    public function deactivate(User $target, User $actor): void
    {
        $ids = DB::transaction(function () use ($target, $actor): array {
            $fresh = User::query()->lockForUpdate()->findOrFail($target->id);

            $this->assertCanManage($fresh, $actor);

            if ($fresh->status !== UserStatus::Active) {
                throw new BusinessRuleException(__('portal.not_active'));
            }

            if ($fresh->portal_role === PortalRole::Administrador
                && User::query()->where('portal_role', PortalRole::Administrador->value)->where('status', UserStatus::Active->value)->lockForUpdate()->count() <= 1) {
                throw new BusinessRuleException(__('portal.last_admin'));
            }

            $fresh->forceFill(['status' => UserStatus::Inactive, 'remember_token' => Str::random(60)])->save();

            $ids = [];
            foreach ($fresh->accesos()->get() as $access) {
                $access->forceFill([
                    'aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0, 'ultimo_error' => null,
                    'accion_pendiente' => UsuarioSubsistema::ACCION_DESACTIVAR,
                ])->save();
                $ids[] = $access->id;
            }

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('deactivated')->log('deactivated');

            return $ids;
        });

        $this->dispatchFor($ids);
    }

    /** Reactiva una cuenta dada de baja (no las rechazadas, que nunca tuvieron acceso: esas se aprueban). */
    public function reactivate(User $target, User $actor): void
    {
        $ids = DB::transaction(function () use ($target, $actor): array {
            $fresh = User::query()->lockForUpdate()->findOrFail($target->id);

            $this->assertCanManage($fresh, $actor);

            if ($fresh->status !== UserStatus::Inactive) {
                throw new BusinessRuleException(__('portal.not_inactive'));
            }

            if (! $fresh->todosLosAccesos()->exists()) {
                throw new BusinessRuleException(__('portal.not_reactivable'));
            }

            $fresh->forceFill(['status' => UserStatus::Active])->save();

            $ids = [];
            foreach ($fresh->accesos()->get() as $access) {
                $never = $access->aprovisionado_at === null && $access->aprovisionamiento !== UsuarioSubsistema::CONFLICTO;
                $access->forceFill([
                    'aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0, 'ultimo_error' => null,
                    'accion_pendiente' => $never ? null : UsuarioSubsistema::ACCION_REACTIVAR,
                ])->save();
                $ids[] = $access->id;
            }

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('reactivated')->log('reactivated');

            return $ids;
        });

        $this->dispatchFor($ids);
    }

    /**
     * Quien puede tocar la cuenta: aprobadores, nunca la propia, y las cuentas con rol amplio (en el portal o en un
     * subsistema) solo un administrador del portal.
     */
    private function assertCanManage(User $target, User $actor): void
    {
        if (! $actor->canApprove()) {
            throw new BusinessRuleException(__('portal.cannot_approve'));
        }

        if ($target->is($actor)) {
            throw new BusinessRuleException(__('portal.cannot_self_manage'));
        }

        if ($actor->portal_role === PortalRole::Administrador) {
            return;
        }

        $broad = $target->portal_role !== PortalRole::Usuario
            || $target->accesos()->get()->contains(fn (UsuarioSubsistema $a): bool => $this->isPrivileged($a->subsistema, $a->rol));

        if ($broad) {
            throw new BusinessRuleException(__('portal.privileged_target'));
        }
    }

    /** Para la interfaz: ¿puede este actor gestionar esta cuenta? (mismas reglas que las acciones). */
    public function canManage(User $target, User $actor): bool
    {
        try {
            $this->assertCanManage($target, $actor);

            return true;
        } catch (BusinessRuleException) {
            return false;
        }
    }

    private function isPrivileged(string $subsistema, string $rol): bool
    {
        return in_array($rol, (array) (Subsistemas::get($subsistema)['roles_privilegiados'] ?? []), true);
    }

    /**
     * Cuenta activa y acceso vigente, bloqueados, con las reglas de quien puede tocarlos.
     *
     * @return array{0: User, 1: UsuarioSubsistema}
     */
    private function lockedAccess(User $target, User $actor, string $subsistema): array
    {
        $fresh = User::query()->lockForUpdate()->findOrFail($target->id);

        $this->assertCanManage($fresh, $actor);

        if ($fresh->status !== UserStatus::Active) {
            throw new BusinessRuleException(__('portal.not_active'));
        }

        $access = $fresh->accesos()->where('subsistema', $subsistema)->lockForUpdate()->first();

        if ($access === null) {
            throw new BusinessRuleException(__('portal.no_such_access'));
        }

        return [$fresh, $access];
    }

    /**
     * Encola lo que corresponde a cada acceso: cambio pendiente -> SyncAccess; alta sin hacer -> ProvisionAccess.
     *
     * @param  list<int>  $ids
     */
    private function dispatchFor(array $ids): void
    {
        foreach ($ids as $id) {
            $access = UsuarioSubsistema::query()->find($id);

            if ($access === null) {
                continue;
            }

            $access->accion_pendiente !== null ? SyncAccess::dispatch($id) : ProvisionAccess::dispatch($id);
        }
    }

    /** Reintento manual de un acceso que quedó en error. */
    public function retry(User $user, User $actor, string $subsistema): void
    {
        if (! $actor->canApprove()) {
            throw new BusinessRuleException(__('portal.cannot_approve'));
        }

        $access = $user->todosLosAccesos()->where('subsistema', $subsistema)->first();

        if ($access === null || $access->aprovisionamiento !== UsuarioSubsistema::ERROR) {
            throw new BusinessRuleException(__('portal.not_retryable'));
        }

        $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0])->save();

        activity('auth')->performedOn($user)->causedBy($actor)->event('provision_retry')
            ->withProperties(['subsistema' => $subsistema])->log('provision_retry');

        $this->dispatchFor([$access->id]);
    }

    public function reject(User $user, User $actor, string $reason): void
    {
        DB::transaction(function () use ($user, $actor, $reason): void {
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->assertDecidable($fresh, $actor);

            $fresh->solicitudAcceso?->forceFill(['estado' => SolicitudAcceso::RECHAZADA])->save();

            $fresh->forceFill([
                'status' => UserStatus::Inactive,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('rejected')
                ->withProperties(['reason' => $reason])->log('rejected');
        });
    }

    /** Solo se decide sobre cuentas pendientes y nunca sobre la propia. */
    private function assertDecidable(User $target, User $actor): void
    {
        if (! $actor->canApprove()) {
            throw new BusinessRuleException(__('portal.cannot_approve'));
        }

        if ($target->is($actor)) {
            throw new BusinessRuleException(__('portal.cannot_self'));
        }

        if ($target->status !== UserStatus::Pending) {
            throw new BusinessRuleException(__('portal.not_pending'));
        }
    }
}
