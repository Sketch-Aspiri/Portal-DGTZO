<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\SubsystemNoticeRequest;
use App\Services\Ecosystem\NotificationHubService;
use Illuminate\Http\JsonResponse;

/** Avisos de los sistemas (puente interno): centro de notificaciones + Web Push para las cuentas activas del portal. */
class SubsystemNoticeController extends Controller
{
    public function __invoke(SubsystemNoticeRequest $request, NotificationHubService $hub): JsonResponse
    {
        $recipients = $hub->send(
            (string) $request->attributes->get('bridge_system'),
            array_values($request->validated('correos')),
            (string) $request->validated('titulo'),
            (string) $request->validated('cuerpo'),
            $request->normalizedUrl(),
            $request->validated('ref'),
            $request->validated('modulo'),
        );

        return response()->json(['recipients' => $recipients]);
    }
}
