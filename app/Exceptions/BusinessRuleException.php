<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Regla de negocio incumplida: se muestra al usuario, no es un error del sistema. */
class BusinessRuleException extends RuntimeException
{
    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['business' => $this->getMessage()]);
    }

    public function report(): bool
    {
        return false;
    }
}
