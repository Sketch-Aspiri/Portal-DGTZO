<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA obligatorio para quienes aprueban cuentas (fuerza la configuración) y desafío en
 * cada sesión para cualquiera que lo haya activado.
 */
final class EnsureTwoFactorVerified
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return $this->deny($request, 'two-factor.setup');
        }

        if ($user->hasTwoFactorEnabled() && ! $this->twoFactor->isVerifiedInSession($user)) {
            return $this->deny($request, 'two-factor.challenge');
        }

        return $next($request);
    }

    private function deny(Request $request, string $route): Response
    {
        if ($request->expectsJson()) {
            abort(403, 'two_factor_required');
        }

        return redirect()->route($route);
    }
}
