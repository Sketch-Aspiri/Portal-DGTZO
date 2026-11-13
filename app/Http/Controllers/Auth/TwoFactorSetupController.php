<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwoFactorSetupController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $setup = $this->twoFactor->prepareSetup($request->user());

        if ($setup === null) {
            return redirect()->route('dashboard');
        }

        return response()->view('auth.two-factor-setup', $setup)
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(TwoFactorCodeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $codes = $this->twoFactor->confirmSetup($user, $request->validated('code'));

        if ($codes === null) {
            return back()->withErrors(['code' => __('portal.two_factor.invalid_code')]);
        }

        // Quien acaba de configurarlo ya demostró el segundo factor en esta sesión.
        $this->twoFactor->markVerifiedInSession($user);

        return redirect()->route('two-factor.recovery')->with('recovery_codes', $codes);
    }

    /** Los códigos de recuperación se muestran una sola vez (vienen en la sesión flash). */
    public function recovery(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! is_array($codes)) {
            return redirect()->route('dashboard');
        }

        return response()->view('auth.two-factor-recovery', ['codes' => $codes])
            ->header('Cache-Control', 'no-store, private');
    }
}
