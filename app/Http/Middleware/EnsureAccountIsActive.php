<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isActive()) {
            if ($request->expectsJson()) {
                abort(403, 'account_not_active');
            }

            return redirect()->route('account.status');
        }

        return $next($request);
    }
}
