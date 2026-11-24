<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Auth\EcosystemSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CalendarioEventoController;
use App\Http\Controllers\LauncherController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Push\PushSubscriptionController;
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

        Route::prefix('notificaciones')->name('notifications.')->group(function () {
            Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
            Route::post('/leer-todas', [NotificationCenterController::class, 'readAll'])->name('read-all');
            Route::put('/preferencias', [NotificationCenterController::class, 'updatePreferences'])->name('preferences');
            Route::get('/{id}', [NotificationCenterController::class, 'open'])->whereUuid('id')->name('open');
        });
        Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->middleware('throttle:push-write')->name('push.subscribe');
        Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:push-write')->name('push.unsubscribe');

        // Calendario oficial: lo consulta toda cuenta activa; solo quien administra el portal lo edita.
        Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendar.index');
        Route::middleware('can:manage-calendar')->prefix('calendario/eventos')->name('calendar.events.')->group(function () {
            Route::get('/nuevo', [CalendarioEventoController::class, 'create'])->name('create');
            Route::post('/', [CalendarioEventoController::class, 'store'])->middleware('throttle:calendar-write')->name('store');
            Route::get('/{evento}/editar', [CalendarioEventoController::class, 'edit'])->name('edit');
            Route::put('/{evento}', [CalendarioEventoController::class, 'update'])->middleware('throttle:calendar-write')->name('update');
            Route::delete('/{evento}', [CalendarioEventoController::class, 'destroy'])->middleware('throttle:calendar-write')->name('destroy');
        });

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [ProfileAvatarController::class, 'update'])->middleware('throttle:avatar-upload')->name('profile.avatar.update');
        Route::delete('/profile/avatar', [ProfileAvatarController::class, 'destroy'])->middleware('throttle:avatar-upload')->name('profile.avatar.destroy');
        Route::get('/avatars/{user}', [ProfileAvatarController::class, 'show'])->middleware('throttle:avatar-view')->name('avatars.show');

        Route::middleware('can:approve-accounts')->prefix('usuarios')->name('users.')->group(function () {
            Route::get('/', [UserApprovalController::class, 'index'])->name('index');
            Route::get('/nueva', [UserApprovalController::class, 'create'])->name('create');
            Route::post('/', [UserApprovalController::class, 'store'])->middleware('throttle:approvals')->name('store');
            Route::get('/{user}', [UserApprovalController::class, 'show'])->name('show');
            Route::put('/{user}/accesos/{subsistema}', [UserApprovalController::class, 'updateAccess'])
                ->where('subsistema', '[a-z_]{2,40}')->middleware('throttle:approvals')->name('access.update');
            Route::delete('/{user}/accesos/{subsistema}', [UserApprovalController::class, 'revokeAccess'])
                ->where('subsistema', '[a-z_]{2,40}')->middleware('throttle:approvals')->name('access.revoke');
            Route::post('/{user}/desactivar', [UserApprovalController::class, 'deactivate'])
                ->middleware('throttle:approvals')->name('deactivate');
            Route::post('/{user}/reactivar', [UserApprovalController::class, 'reactivate'])
                ->middleware('throttle:approvals')->name('reactivate');
            Route::post('/{user}/enlace-contrasena', [UserApprovalController::class, 'invite'])
                ->middleware('throttle:approvals')->name('invite');
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
