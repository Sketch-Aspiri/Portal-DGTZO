<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\AvatarService;
use App\Services\Ecosystem\BridgeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reparte la foto vigente (o su retiro) a los subsistemas donde la persona tiene acceso, salvo el que originó el
 * cambio. Siempre manda lo ÚLTIMO guardado, así que un reintento tardío nunca pisa una foto más nueva.
 */
class PushAvatar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 180, 900];
    }

    public function __construct(public readonly int $userId, public readonly ?string $origin = null)
    {
        $this->afterCommit();
    }

    public function handle(BridgeClient $bridge, AvatarService $avatars): void
    {
        $user = User::query()->with('accesos')->find($this->userId);

        if ($user === null || ! $user->isActive()) {
            return;
        }

        $bytes = $avatars->bytes($user);
        $failed = false;

        foreach ($user->accesos->pluck('subsistema')->unique() as $subsystem) {
            if ($subsystem === $this->origin || ! in_array($subsystem, (array) config('ecosistema.avatares.subsistemas'), true)) {
                continue;
            }

            $body = ['email' => mb_strtolower((string) $user->email)];
            $response = $bytes === null
                ? $bridge->send($subsystem, 'DELETE', '/interno/portal/avatar', body: $body)
                : $bridge->send($subsystem, 'PUT', '/interno/portal/avatar', body: $body + ['imagen' => base64_encode($bytes)]);

            // 404: la persona aún no tiene cuenta allá; no es un error que se arregle reintentando.
            if ($response === null || ($response->serverError())) {
                $failed = true;
            }
        }

        if ($failed) {
            $this->release($this->backoff()[min($this->attempts() - 1, 2)]);
        }
    }
}
