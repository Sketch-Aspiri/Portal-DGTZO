<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly UserRegistrationService $registration) {}

    public function create(): View
    {
        return view('auth.register');
    }

    /** La cuenta nace pendiente y sin sesión: la aprueba Jefe de Zona o Administrador. */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $this->registration->register($request->validated());

        return redirect()->route('login')->with('status', __('portal.registered'));
    }
}
