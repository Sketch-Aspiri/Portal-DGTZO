<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\UsuarioSubsistema;
use App\Services\Ecosystem\SubsystemGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Crea en un subsistema la cuenta aprobada en el portal. Idempotente (el subsistema responde "ya aprovisionada" si
 * se repite) y con reintentos solo ante fallas de red o 5xx. Un conflicto de correo no se reintenta.
 */
class ProvisionAccess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(public readonly int $accessId)
    {
        $this->afterCommit();
    }

    public function handle(SubsystemGateway $gateway): void
    {
        $access = UsuarioSubsistema::query()->with('user')->find($this->accessId);

        // Ya resuelto o la cuenta del portal dejó de estar activa (p. ej. se revocó): nada que hacer.
        if ($access === null || $access->user === null || ! $access->user->isActive() || $access->aprovisionamiento === UsuarioSubsistema::OK) {
            return;
        }

        $result = $gateway->provision($access->user, $access);

        DB::transaction(function () use ($access, $result): void {
            $access->forceFill([
                'aprovisionamiento' => $result['estado'],
                'aprovisionado_at' => $result['estado'] === UsuarioSubsistema::OK ? now() : $access->aprovisionado_at,
                'ultimo_error' => $result['mensaje'],
                'intentos' => min(255, $access->intentos + 1),
            ])->save();

            activity('auth')->performedOn($access->user)->event('provisioned')
                ->withProperties(['subsistema' => $access->subsistema, 'estado' => $result['estado']])->log('provisioned');
        });

        if ($result['reintentar']) {
            // Lanza para que la cola respete `backoff`; al agotar los intentos queda en `error` con su mensaje.
            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
        }
    }
}
