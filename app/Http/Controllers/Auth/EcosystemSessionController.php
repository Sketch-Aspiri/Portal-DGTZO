<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EcosystemSessionRequest;
use App\Services\Ecosystem\EcosystemSignature;
use App\Services\Ecosystem\GlobalSignOut;
use App\Services\Ecosystem\SingleSignOnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio y cierre de sesión únicos. Las rutas son GET porque llegan por redirección del navegador desde
 * otro sistema; ninguna confía en datos sin firma.
 */
class EcosystemSessionController extends Controller
{
    public function __construct(
        private readonly SingleSignOnService $sso,
        private readonly EcosystemSignature $signature,
        private readonly GlobalSignOut $signOut,
    ) {}

    /** Un subsistema pregunta si aquí hay una sesión completa con acceso a él. */
    public function ask(EcosystemSessionRequest $request): RedirectResponse
    {
        $target = (string) $request->validated('para');

        if ($target === '') {
            abort(404);
        }

        $mfa = $this->sso->enabled() ? $this->sso->completeSession($request) : null;
        $user = $request->user();

        // Sin sesión completa o sin acceso al subsistema: se responde "sin sesión" y allá se muestra su login.
        if ($mfa === null || $user === null || ! $this->sso->hasAccessTo($user, $target)) {
            return redirect()->away($this->sso->url($target, '/ecosistema/entrar?sin_sesion=1'));
        }

        $code = $this->signature->sign($target, EcosystemSignature::ENTER, (string) $user->email, $mfa, $request->validated('estado'));

        activity('auth')->performedOn($user)->causedBy($user)->event('sso_issued')
            ->withProperties(['para' => $target, 'ip' => $request->ip()])->log('sso_issued');

        return redirect()->away($this->sso->url($target, '/ecosistema/entrar?codigo='.urlencode($code)));
    }

    /** Se cerró la sesión en un subsistema: se cierra aquí si es la misma persona y se vuelve al login. */
    public function leave(EcosystemSessionRequest $request): RedirectResponse
    {
        $data = $this->signature->verify($request->validated('codigo'), EcosystemSignature::LEAVE);
        $user = $request->user();

        $response = redirect()->route('login');

        if ($data !== null && $user !== null && mb_strtolower((string) $user->email) === $data['email']) {
            // El sistema que originó el cierre ya cerró; se cierran los demás, de uno en uno.
            $queue = $this->signOut->queueFor($user, $data['iss']);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($queue !== null) {
                $response->withCookie($queue);
            }
        }

        return $response;
    }
}
