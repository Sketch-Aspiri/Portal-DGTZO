<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationFailed;

/**
 * El paquete ya borra la suscripción cuando el servicio push responde 404/410 (dispositivo dado de baja). Aquí solo se
 * deja constancia, sin endpoint, llaves ni datos de la persona.
 */
class LogFailedPush
{
    public function handle(NotificationFailed $event): void
    {
        Log::info('push: envío fallido', [
            'expirada' => $event->report->isSubscriptionExpired(),
            'estado' => $event->report->getResponse()?->getStatusCode(),
        ]);
    }
}
