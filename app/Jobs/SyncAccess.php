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
 * Lleva a un subsistema el cambio pendiente de un acceso (rol/ubicacion, quitar acceso, baja o reactivacion de la
 * cuenta). Lo que se envia sale del estado actual de la fila, no del momento en que se encolo: si el cambio ya se
 * resolvio o se reemplazo, no hace nada (idempotente). Reintenta solo ante fallas de red o 5xx.
 */
class SyncAccess implements ShouldQueue
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

        if ($access === null || $access->user === null || $access->accion_pendiente === null) {
            return;
        }

        $accion = $access->accion_pendiente;
        $result = $gateway->sync($access->user, $access, $accion);

        DB::transaction(function () use ($access, $accion, $result): void {
            // Si mientras tanto se pidio otro cambio, ese manda: no se borra la accion nueva.
            $current = UsuarioSubsistema::query()->lockForUpdate()->find($access->id);
            $superseded = $current === null || $current->accion_pendiente !== $accion;

            if ($current !== null && ! $superseded) {
                $current->forceFill([
                    'aprovisionamiento' => $result['estado'],
                    // Resuelto: nada pendiente. Con error se conserva la accion para el reintento manual.
                    'accion_pendiente' => $result['estado'] === UsuarioSubsistema::OK ? null : $accion,
                    'ultimo_error' => $result['mensaje'],
                    'intentos' => min(255, $current->intentos + 1),
                ])->save();
            }

            activity('auth')->performedOn($access->user)->event('synced')
                ->withProperties(['subsistema' => $access->subsistema, 'accion' => $accion, 'estado' => $result['estado']])->log('synced');
        });

        if ($result['reintentar']) {
            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
        }
    }
}
