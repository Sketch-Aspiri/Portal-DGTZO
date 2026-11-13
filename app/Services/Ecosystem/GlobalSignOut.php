<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cierre de sesión único: al salir del portal (o de un subsistema, que avisa al portal) se cierra la sesión
 * en los demás subsistemas de la persona. Cada cierre es un salto del navegador (/ecosistema/salir con un
 * código firmado), así que los pendientes viajan en una cookie cifrada de corta vida y se recorren de uno
 * en uno: cada subsistema, al terminar, vuelve al /login del portal, que sigue con el siguiente.
 */
final class GlobalSignOut
{
    public const COOKIE = 'portal_salida';

    public function __construct(
        private readonly EcosystemSignature $signature,
        private readonly SingleSignOnService $sso,
    ) {}

    /** Prepara la cadena de cierres para $user, sin contar el sistema $origin (que ya cerró). */
    public function queueFor(User $user, ?string $origin = null): ?Cookie
    {
        if (! $this->sso->enabled()) {
            return null;
        }

        $pending = $user->accesos()->pluck('subsistema')
            ->reject(fn (string $key): bool => $key === $origin || ! array_key_exists($key, (array) config('ecosistema.urls')))
            ->values()->all();

        if ($pending === []) {
            return null;
        }

        return $this->cookie(['email' => mb_strtolower((string) $user->email), 'pending' => $pending]);
    }

    /** Salta al siguiente subsistema pendiente, o null si la cadena terminó (o no hay). */
    public function next(Request $request): ?RedirectResponse
    {
        $state = json_decode((string) $request->cookie(self::COOKIE), true);

        if (! is_array($state) || ! is_string($state['email'] ?? null) || ! is_array($state['pending'] ?? null)) {
            return null;
        }

        $pending = array_values(array_filter($state['pending'], is_string(...)));
        $target = array_shift($pending);

        if ($target === null || ! array_key_exists($target, (array) config('ecosistema.urls'))) {
            return redirect()->route('login')->withoutCookie(self::COOKIE);
        }

        $code = $this->signature->sign($target, EcosystemSignature::LEAVE, $state['email'], false);
        $cookie = $pending === []
            ? Cookie::create(self::COOKIE, '', 1)
            : $this->cookie(['email' => $state['email'], 'pending' => $pending]);

        return redirect()->away($this->sso->url($target, '/ecosistema/salir?codigo='.urlencode($code)))->withCookie($cookie);
    }

    /** @param  array{email: string, pending: list<string>}  $state */
    private function cookie(array $state): Cookie
    {
        // Vive 2 minutos: solo debe cubrir los saltos de este cierre.
        return Cookie::create(self::COOKIE, json_encode($state, JSON_THROW_ON_ERROR), now()->addMinutes(2), '/', null, null, true, false, Cookie::SAMESITE_LAX);
    }
}
