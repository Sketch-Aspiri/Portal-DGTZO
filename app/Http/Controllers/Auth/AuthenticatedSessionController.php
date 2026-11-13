<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Ecosystem\GlobalSignOut;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly GlobalSignOut $signOut,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        // Cierre de sesión único en curso: se sigue con el siguiente subsistema antes de mostrar el login.
        return $this->signOut->next($request) ?? view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $this->twoFactor->clearSessionVerification();

        $user = $request->user();
        activity('auth')->performedOn($user)->causedBy($user)->event('login')
            ->withProperties(['ip' => $request->ip()])->log('login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $queue = $user !== null ? $this->signOut->queueFor($user) : null;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Con subsistemas abiertos, el cierre pasa por ellos (vía /login) antes de volver a la portada.
        $response = $queue !== null ? redirect()->route('login') : redirect('/');

        return $queue !== null ? $response->withCookie($queue) : $response;
    }
}
