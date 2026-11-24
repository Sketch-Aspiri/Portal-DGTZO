<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\SubsystemNotice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Centro de avisos del ecosistema: el Dashboard y Tickets mandan aquí sus eventos (puente firmado) y el portal los
 * guarda en el centro de notificaciones y los entrega por Web Push. Solo llegan a cuentas ACTIVAS del portal; quien
 * no existe o no está activa se omite en silencio (no se revela qué correos existen).
 */
final class NotificationHubService
{
    /** Sistemas que pueden emitir avisos y que cada persona puede silenciar. */
    public const SYSTEMS = ['dashboard', 'tickets'];

    /** Avisos que genera el propio portal (no entran por el puente) y que también se pueden silenciar. */
    public const LOCAL_SOURCES = ['calendario'];

    /**
     * Fuentes que la persona puede silenciar en su centro de notificaciones.
     *
     * @return list<string>
     */
    public static function silenciables(): array
    {
        return [...self::SYSTEMS, ...self::LOCAL_SOURCES];
    }

    /**
     * @param  list<string>  $emails
     * @return int Cuentas a las que se envió el aviso.
     */
    public function send(string $source, array $emails, string $title, string $body, string $url, ?string $ref, ?string $module = null): int
    {
        // Idempotencia: el emisor reintenta si no recibe la respuesta; el mismo `ref` no avisa dos veces.
        $key = $ref === null ? null : 'portal:aviso:'.hash('sha256', $source.'|'.$ref);

        if ($key !== null && ! Cache::add($key, true, now()->addDay())) {
            return 0;
        }

        try {
            return $this->deliver($source, $emails, $title, $body, $url, $module);
        } catch (Throwable $e) {
            // Si el envío falla, el reintento del emisor con el mismo `ref` debe poder enviarlo.
            if ($key !== null) {
                Cache::forget($key);
            }

            throw $e;
        }
    }

    /**
     * @param  list<string>  $emails
     */
    private function deliver(string $source, array $emails, string $title, string $body, string $url, ?string $module): int
    {
        $normalized = collect($emails)->map(fn (string $email): string => mb_strtolower($email))->unique()->values();

        $users = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereIn(DB::raw('LOWER(email)'), $normalized->all())
            ->get()
            ->unique(fn (User $user): int|string => $user->getKey())
            ->values();

        if ($users->isEmpty()) {
            return 0;
        }

        Notification::send($users, new SubsystemNotice($title, $body, $url, $source, $module));

        activity('ecosistema')->event('notice_sent')
            ->withProperties(['source' => $source, 'recipients' => $users->count()])
            ->log('notice_sent');

        return $users->count();
    }
}
