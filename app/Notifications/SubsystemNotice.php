<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Aviso de un sistema (Dashboard o Tickets) que llega por el puente: queda en el centro de notificaciones del portal
 * y llega al celular por Web Push, salvo que la persona haya silenciado ese sistema (entonces solo queda en el
 * centro). Los textos los redacta el sistema emisor: cortos (título <= 60, cuerpo <= 120) y sin datos sensibles,
 * porque se ven en la pantalla bloqueada. La URL ya viene normalizada por EcosystemUrl. Solo escalares.
 */
final class SubsystemNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'subsystem_notice';

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $source,
        public readonly ?string $module = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        $muted = method_exists($notifiable, 'mutesPush') && $notifiable->mutesPush($this->source);

        return $muted ? ['database'] : ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'source' => $this->source,
            'module' => $this->module,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->body($this->body)
            ->tag('sys-'.substr(sha1($this->url), 0, 12))
            ->data(['url' => $this->url]);
    }
}
