<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Política única del ecosistema: la del Dashboard (plan de perfil único).
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers()->symbols();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        Gate::define('approve-accounts', fn (User $user): bool => $user->canApprove());

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('ecosystem-sso', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('access-request', fn (Request $request) => Limit::perHour(10)->by((string) $request->user()?->id));
        RateLimiter::for('avatar-upload', fn (Request $request) => Limit::perHour(20)->by((string) $request->user()?->id));
        RateLimiter::for('avatar-view', fn (Request $request) => Limit::perMinute(600)->by((string) $request->user()?->id));
        RateLimiter::for('approvals', fn (Request $request) => Limit::perMinute(30)->by((string) $request->user()?->id));
    }
}
