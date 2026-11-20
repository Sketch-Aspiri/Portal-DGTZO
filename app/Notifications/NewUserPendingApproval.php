<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserPendingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $applicant, private readonly bool $withRequest = false)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('portal.pending_mail.subject'))
            ->line(__('portal.pending_mail.line', ['name' => $this->applicant->name]))
            ->when($this->withRequest, fn (MailMessage $m) => $m->line(__('portal.pending_mail.with_request')))
            ->action(__('portal.pending_mail.action'), route('users.show', $this->applicant));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['user_id' => $this->applicant->id, 'name' => $this->applicant->name, 'con_solicitud' => $this->withRequest];
    }
}
