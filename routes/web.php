<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Auth\EcosystemSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\LauncherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserApprovalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Inicio y cierre de sesión únicos: llegan por redirección desde Dashboard/Tickets; sin sesión previa ni CSRF.
Route::prefix('ecosistema')->middleware('throttle:ecosystem-sso')->group(function () {
    Route::get('/sso', [EcosystemSessionController::class, 'ask'])->name('ecosistema.sso');
    Route::get('/salir', [EcosystemSessionController::class, 'leave'])->name('ecosistema.salir');
});

Route::middleware('auth')->group(function () {
    // Cuenta pendiente, rechazada o inactiva: solo ve su estado y puede salir.
    Route::get('/cuenta', AccountStatusController::class)->name('account.status');
    Route::put('/cuenta/solicitud', [AccessRequestController::class, 'store'])->middleware('throttle:access-request')->name('account.request');

    Route::middleware('account.active')->group(function () {
        // 2FA: configurar (obligatorio para quienes aprueban) y superar el desafío en cada sesión.
        Route::middleware('throttle:two-factor')->group(function () {
            Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
            Route::post('/two-factor/setup', [TwoFactorSetupController::class, 'store']);
            Route::get('/two-factor/recovery', [TwoFactorSetupController::class, 'recovery'])->name('two-factor.recovery');
            Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
            Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store']);
        });
    });

    Route::middleware(['account.active', 'two-factor'])->group(function () {
        Route::get('/inicio', LauncherController::class)->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::middleware('can:approve-accounts')->prefix('usuarios')->name('users.')->group(function () {
            Route::get('/', [UserApprovalController::class, 'index'])->name('index');
            Route::get('/{user}', [UserApprovalController::class, 'show'])->name('show');
            Route::post('/{user}/aprobar', [UserApprovalController::class, 'approve'])
                ->middleware('throttle:approvals')->name('approve');
            Route::post('/{user}/rechazar', [UserApprovalController::class, 'reject'])
                ->middleware('throttle:approvals')->name('reject');
            Route::post('/{user}/accesos', [UserApprovalController::class, 'grant'])
                ->middleware('throttle:approvals')->name('grant');
            Route::post('/{user}/aprovisionar/{subsistema}', [UserApprovalController::class, 'retry'])
                ->where('subsistema', '[a-z_]{2,40}')->middleware('throttle:approvals')->name('retry');
        });
    });
});

require __DIR__.'/auth.php';
