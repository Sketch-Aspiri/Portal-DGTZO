<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso a quien ya tenía cuenta en Dashboard o Tickets: ahora entra con una sola contraseña, la del portal. */
class AccountMigrated extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('portal.migrated_mail.subject'))
            ->greeting(__('portal.migrated_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('portal.migrated_mail.line1'))
            ->line(__('portal.migrated_mail.line2'))
            ->action(__('portal.migrated_mail.action'), route('password.request'))
            ->line(__('portal.migrated_mail.line3'));
    }
}
