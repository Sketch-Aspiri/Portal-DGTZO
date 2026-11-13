<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Códigos firmados del inicio y cierre de sesión únicos. Mismo formato que App\Services\Ecosystem\EcosystemSignature
 * de Tickets y FirmaEcosistema del Dashboard (no cambiar uno sin los otros):
 *
 *   base64url(json{v, iss, aud, act, email, mfa, state, lnk, nonce, exp}) . "." . base64url(hmac_sha256)
 *
 * Un código vale para un solo destino, una sola acción, 60 s y una sola vez (el nonce se consume en caché).
 */
final class EcosystemSignature
{
    public const ENTER = 'entrar';

    public const LEAVE = 'salir';

    public function configured(): bool
    {
        return strlen((string) config('ecosistema.sso.secreto')) >= 32;
    }

    public function sign(string $destino, string $accion, string $email, bool $mfa, ?string $estado = null, bool $linked = true): string
    {
        $carga = $this->b64(json_encode([
            'v' => 1,
            'iss' => config('ecosistema.sistema'),
            'aud' => $destino,
            'act' => $accion,
            'email' => mb_strtolower($email),
            'mfa' => $mfa,
            'state' => $estado,
            // Las cuentas del portal solo existen tras la aprobación de la Zona: están "vinculadas".
            'lnk' => $linked,
            'nonce' => Str::random(32),
            'exp' => now()->addSeconds((int) config('ecosistema.sso.vigencia', 60))->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return $carga.'.'.$this->b64(hash_hmac('sha256', $carga, $this->secreto(), true));
    }

    /**
     * Devuelve la carga si el código es auténtico, va dirigido a este sistema, es de la acción esperada,
     * no venció y no se usó antes; si no, null.
     *
     * @return array{iss: string, email: string}|null
     */
    public function verify(?string $codigo, string $accion): ?array
    {
        if (! $this->configured() || ! is_string($codigo) || strlen($codigo) > 2048 || substr_count($codigo, '.') !== 1) {
            return null;
        }

        [$carga, $firma] = explode('.', $codigo);

        if (! hash_equals($this->b64(hash_hmac('sha256', $carga, $this->secreto(), true)), $firma)) {
            return null;
        }

        $datos = json_decode((string) base64_decode(strtr($carga, '-_', '+/'), true), true);

        if (! is_array($datos)
            || ($datos['v'] ?? null) !== 1
            || ($datos['aud'] ?? null) !== config('ecosistema.sistema')
            || ($datos['act'] ?? null) !== $accion
            || ! array_key_exists((string) ($datos['iss'] ?? ''), (array) config('ecosistema.urls'))
            || ($datos['iss'] ?? null) === config('ecosistema.sistema')
            || ! is_string($datos['email'] ?? null)
            || ! is_string($datos['nonce'] ?? null)
            || ! is_int($datos['exp'] ?? null)
            || $datos['exp'] < now()->getTimestamp()) {
            return null;
        }

        // Un solo uso: el primero que lo canjea gana.
        if (! Cache::add('ecosistema:nonce:'.hash('sha256', $datos['nonce']), true, 300)) {
            return null;
        }

        return ['iss' => $datos['iss'], 'email' => $datos['email']];
    }

    private function secreto(): string
    {
        return (string) config('ecosistema.sso.secreto');
    }

    private function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
