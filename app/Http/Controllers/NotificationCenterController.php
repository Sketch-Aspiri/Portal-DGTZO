<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Notifications\SubsystemNotice;
use App\Services\Ecosystem\NotificationHubService;
use App\Support\EcosystemUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de notificaciones del portal: historial unificado de los avisos de todos los sistemas, marcar como leídas y
 * silenciar el push de cada sistema. Siempre opera sobre las notificaciones del usuario autenticado.
 */
class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $muted = $user->notificationPreferences()->where('muted', true)->pluck('subsistema')->all();

        return view('notifications.index', [
            'notifications' => $user->notifications()->where('data->type', SubsystemNotice::TYPE)->latest()->paginate(20),
            'unread' => $user->unreadNotifications()->where('data->type', SubsystemNotice::TYPE)->count(),
            'systems' => NotificationHubService::SYSTEMS,
            'muted' => $muted,
        ]);
    }

    /** Marca como leída y lleva al destino; una URL que ya no es válida lleva de vuelta al centro. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $target = EcosystemUrl::normalize((string) ($notification->data['url'] ?? ''));

        return $target === null ? redirect()->route('notifications.index') : redirect()->away(EcosystemUrl::absolute($target));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        foreach (NotificationHubService::SYSTEMS as $system) {
            $user->notificationPreferences()->updateOrCreate(
                ['subsistema' => $system],
                ['muted' => $request->boolean('muted.'.$system)],
            );
        }

        return redirect()->route('notifications.index')->with('status', __('push.center.saved'));
    }
}
