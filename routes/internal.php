<?php

use App\Http\Controllers\Internal\SubsystemAvatarController;
use App\Http\Controllers\Internal\SubsystemNoticeController;
use Illuminate\Support\Facades\Route;

/*
 * Puente de los subsistemas hacia el portal: solo de servidor a servidor, firmado con el secreto del subsistema
 * (VerifySubsystemBridge). Fuera del grupo `web`: sin sesión ni CSRF.
 */
Route::put('avatar', [SubsystemAvatarController::class, 'update'])->name('avatar.update');
Route::delete('avatar', [SubsystemAvatarController::class, 'destroy'])->name('avatar.destroy');
// Avisos de los sistemas (Dashboard, Tickets): centro de notificaciones + Web Push de las cuentas activas del portal.
Route::post('notificaciones', SubsystemNoticeController::class)->middleware('throttle:ecosystem-bridge')->name('notificaciones.store');
