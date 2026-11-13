<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ecosystem\SubsystemGateway;
use App\Support\Subsistemas;
use Illuminate\Console\Command;

/**
 * Simulación del emparejado de cuentas (SOLO LECTURA): cruza por correo las cuentas de cada subsistema con las del
 * portal y reporta qué pasaría. No escribe nada, ni aquí ni allá. Las cuentas que ya existen en un subsistema se
 * vinculan con su contraseña la primera vez que entran (no por el correo solo: no está verificado).
 */
class PairingSimulationCommand extends Command
{
    protected $signature = 'portal:emparejar-simulacion {--listar : Muestra también los correos de cada grupo}';

    protected $description = 'Reporta (sin cambiar nada) cómo se cruzan por correo las cuentas del portal con las de cada subsistema';

    public function handle(SubsystemGateway $gateway): int
    {
        $portal = User::query()->get(['email', 'status'])->mapWithKeys(
            fn (User $user): array => [mb_strtolower((string) $user->email) => $user->status->value],
        );

        $this->info('Cuentas en el portal: '.$portal->count());

        $failed = false;

        foreach (Subsistemas::claves() as $key) {
            $accounts = $gateway->accounts($key);

            if ($accounts === null) {
                $this->error("{$key}: no responde o el puente no está configurado.");
                $failed = true;

                continue;
            }

            $this->report($key, $accounts, $portal->all());
        }

        $this->newLine();
        $this->line('Simulación: no se cambió nada.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @param  array<string, string>  $portal  correo => estado
     */
    private function report(string $key, array $accounts, array $portal): void
    {
        $bySubsystem = collect($accounts)->keyBy(fn (array $a): string => mb_strtolower((string) $a['email']));

        $both = $bySubsystem->filter(fn ($a, string $email): bool => isset($portal[$email]));
        $linked = $both->filter(fn (array $a): bool => (bool) ($a['vinculada'] ?? false));
        $onlyThere = $bySubsystem->reject(fn ($a, string $email): bool => isset($portal[$email]));
        $onlyPortal = collect($portal)->reject(fn ($status, string $email): bool => $bySubsystem->has($email));

        $this->newLine();
        $this->info(Subsistemas::get($key)['nombre'].': '.$bySubsystem->count().' cuentas');
        $this->table(['Grupo', 'Cuentas', 'Qué pasaría'], [
            ['En los dos', $both->count(), 'Ya hay cuenta allá: se vincula con su contraseña al entrar (ya vinculadas: '.$linked->count().')'],
            ['Solo en el subsistema', $onlyThere->count(), 'Necesitan crear su cuenta en el portal y ser aprobadas; al aprobar se detecta el conflicto de correo'],
            ['Solo en el portal', $onlyPortal->count(), 'Se crean allá al aprobarlas (si aún no tienen acceso a este sistema, no pasa nada)'],
        ]);

        if ($this->option('listar')) {
            foreach (['En los dos' => $both, 'Solo en el subsistema' => $onlyThere] as $label => $group) {
                $this->line($label.': '.($group->keys()->sort()->implode(', ') ?: '—'));
            }
        }
    }
}
