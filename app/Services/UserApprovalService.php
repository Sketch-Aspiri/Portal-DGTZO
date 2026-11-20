<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\ProvisionAccess;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use Illuminate\Support\Facades\DB;

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
                if ($fresh->accesos()->where('subsistema', $subsistema)->exists()) {
                    throw new BusinessRuleException(__('portal.already_has_access'));
                }

                $access = $fresh->accesos()->create(['subsistema' => $subsistema, 'rol' => $datos['rol'], 'contexto' => $datos['contexto']]);
                $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0])->save();
                $ids[] = $access->id;
            }

            activity('auth')->performedOn($fresh)->causedBy($actor)->event('access_granted')
                ->withProperties(['accesos' => array_map(fn (array $d): array => ['rol' => $d['rol'], 'contexto' => $d['contexto']], $accesos)])
                ->log('access_granted');

            return $ids;
        });

        foreach ($created as $id) {
            ProvisionAccess::dispatch($id);
        }
    }

    /** Reintento manual de un acceso que quedó en error. */
    public function retry(User $user, User $actor, string $subsistema): void
    {
        if (! $actor->canApprove()) {
            throw new BusinessRuleException(__('portal.cannot_approve'));
        }

        $access = $user->accesos()->where('subsistema', $subsistema)->first();

        if ($access === null || $access->aprovisionamiento !== UsuarioSubsistema::ERROR) {
            throw new BusinessRuleException(__('portal.not_retryable'));
        }

        $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::PENDIENTE, 'intentos' => 0])->save();

        activity('auth')->performedOn($user)->causedBy($actor)->event('provision_retry')
            ->withProperties(['subsistema' => $subsistema])->log('provision_retry');

        ProvisionAccess::dispatch($access->id);
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
