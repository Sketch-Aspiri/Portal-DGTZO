<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Solo lectura: cuánta gente del portal ya puede recibir el push general. Sirve para decidir cuándo cambiar el
 * Dashboard y Tickets a enviar solo por el portal (quien no tenga cuenta o dispositivo suscrito deja de recibir push).
 * Los correos salen enmascarados.
 */
class NotificationCoverageCommand extends Command
{
    protected $signature = 'notificaciones:cobertura {--detalle : lista (enmascarados) a quienes aún no tienen dispositivo}';

    protected $description = 'Cuántas cuentas activas del portal ya tienen un dispositivo con notificaciones push';

    public function handle(): int
    {
        $active = User::query()->where('status', UserStatus::Active->value)->count();
        $subscribed = PushSubscription::query()->where('subscribable_type', (new User)->getMorphClass())
            ->distinct()->count('subscribable_id');
        $withoutDevice = User::query()->where('status', UserStatus::Active->value)->whereDoesntHave('pushSubscriptions');

        $this->line("Cuentas activas del portal: {$active}");
        $this->line("Con al menos un dispositivo suscrito: {$subscribed}");
        $this->line('Sin dispositivo: '.(clone $withoutDevice)->count());

        foreach (['dashboard', 'tickets'] as $system) {
            $this->line(sprintf('Con acceso a %s: %d', $system, User::query()->where('status', UserStatus::Active->value)
                ->whereHas('accesos', fn ($q) => $q->where('subsistema', $system))->count()));
        }

        if ($this->option('detalle')) {
            $this->newLine();
            $this->line('Sin dispositivo:');
            $withoutDevice->orderBy('id')->pluck('email')->each(fn (string $email) => $this->line('  '.$this->mask($email)));
        }

        $this->newLine();
        $this->line('Las personas que existen en Dashboard/Tickets y aún no tienen cuenta en el portal salen con portal:emparejar-simulacion.');

        return self::SUCCESS;
    }

    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
