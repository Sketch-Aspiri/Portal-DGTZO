<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AccessRequestRequest;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\NewUserPendingApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class AccessRequestController extends Controller
{
    /** Guarda (o corrige, mientras siga pendiente) la solicitud de acceso de la cuenta. */
    public function store(AccessRequestRequest $request): RedirectResponse
    {
        $user = $request->user();
        $solicitud = SolicitudAcceso::query()->firstOrNew(['user_id' => $user->id]);
        $first = ! $solicitud->exists;
        $solicitud->fill([
            'puesto' => $request->validated('puesto'),
            'ubicacion' => $request->validated('ubicacion'),
            'telefono' => $request->validated('telefono'),
            'funciones' => $request->validated('funciones'),
            'justificacion' => $request->validated('justificacion'),
            'accesos' => $request->accesos(),
        ]);
        $solicitud->forceFill(['user_id' => $user->id, 'estado' => SolicitudAcceso::ENVIADA, 'enviada_at' => now()])->save();

        activity('auth')->performedOn($user)->causedBy($user)->event('access_requested')
            ->withProperties(['sistemas' => array_keys($request->accesos())])->log('access_requested');

        if ($first) {
            Notification::send(User::approvers()->get(), new NewUserPendingApproval($user, true));
        }

        return redirect()->route('account.status')->with('status', __('portal.access_request.sent'));
    }
}
