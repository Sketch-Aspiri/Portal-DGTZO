<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use App\Models\User;
use App\Models\UsuarioSubsistema;
use Illuminate\Support\Facades\Cache;

/**
 * Lo que el portal le pide a cada subsistema por el puente: su catálogo (para ubicar a la persona al aprobar) y la
 * creación de la cuenta ya aprobada.
 */
final class SubsystemGateway
{
    public function __construct(private readonly BridgeClient $bridge) {}

    /**
     * Catálogo del subsistema (roles, equipos, estaciones) o null si no responde. Se guarda poco tiempo.
     *
     * @return array<string, mixed>|null
     */
    public function catalog(string $subsystem): ?array
    {
        return Cache::remember('portal:catalogo:'.$subsystem, (int) config('ecosistema.puente.cache_catalogo', 60), function () use ($subsystem): ?array {
            $response = $this->bridge->send($subsystem, 'GET', '/interno/portal/catalogo');

            return $response?->successful() && is_array($response->json()) ? $response->json() : null;
        }) ?: null;
    }

    /**
     * Crea la cuenta en el subsistema.
     *
     * @return array{estado: string, reintentar: bool, mensaje: ?string}
     */
    public function provision(User $user, UsuarioSubsistema $access): array
    {
        $context = (array) $access->contexto;
        $body = [
            'portal_user_id' => $user->portal_user_id,
            'name' => $user->name,
            'email' => mb_strtolower((string) $user->email),
            // Hash bcrypt (no la contraseña): la persona entra al subsistema con la misma contraseña.
            'password_hash' => $user->password,
            'role' => $access->rol,
        ];

        $body += match ($access->subsistema) {
            'tickets' => ['team_ids' => $context['team_ids'] ?? [], 'coordination_ids' => $context['coordination_ids'] ?? []],
            'dashboard' => ['estacion_id' => $context['estacion_id'] ?? null],
            default => [],
        };

        $response = $this->bridge->send($access->subsistema, 'POST', '/interno/portal/cuentas', body: $body);

        if ($response === null) {
            return ['estado' => UsuarioSubsistema::ERROR, 'reintentar' => true, 'mensaje' => __('portal.provision.unreachable')];
        }

        if ($response->successful()) {
            return ['estado' => UsuarioSubsistema::OK, 'reintentar' => false, 'mensaje' => null];
        }

        if ($response->status() === 409) {
            return ['estado' => UsuarioSubsistema::CONFLICTO, 'reintentar' => false, 'mensaje' => __('portal.provision.conflict')];
        }

        // 422 (datos que el subsistema no acepta) o 401/403 (secreto): reintentar no lo arregla, pero un reintento
        // manual tras corregir sí. Solo 5xx se reintenta solo.
        return [
            'estado' => UsuarioSubsistema::ERROR,
            'reintentar' => $response->serverError(),
            'mensaje' => __('portal.provision.rejected', ['code' => $response->status()]),
        ];
    }

    /**
     * Cuentas del subsistema, para la simulación de emparejado (sin contraseñas ni 2FA).
     *
     * @return list<array<string, mixed>>|null null si el subsistema no responde
     */
    public function accounts(string $subsystem): ?array
    {
        $accounts = [];
        $after = 0;

        for ($page = 0; $page < 100; $page++) {
            $response = $this->bridge->send($subsystem, 'GET', '/interno/portal/cuentas', ['despues' => $after, 'limite' => 500]);

            if ($response === null || ! $response->successful()) {
                return null;
            }

            $accounts = [...$accounts, ...(array) $response->json('cuentas', [])];
            $after = $response->json('siguiente');

            if (! is_int($after)) {
                return $accounts;
            }
        }

        return $accounts;
    }
}
