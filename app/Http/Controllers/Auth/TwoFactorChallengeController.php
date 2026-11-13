<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled() || $this->twoFactor->isVerifiedInSession($user)) {
            return redirect()->route('dashboard');
        }

        return response()->view('auth.two-factor-challenge')->header('Cache-Control', 'no-store, private');
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $request->user();

        $passed = $request->filled('recovery_code')
            ? $this->twoFactor->passesRecoveryChallenge($user, (string) $request->validated('recovery_code'))
            : $this->twoFactor->passesChallenge($user, (string) $request->validated('code'));

        if (! $passed) {
            return back()->withErrors(['code' => __('portal.two_factor.invalid_code')]);
        }

        $this->twoFactor->markVerifiedInSession($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
