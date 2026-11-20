<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use App\Notifications\AccountMigrated;
use App\Services\Ecosystem\SubsystemGateway;
use App\Support\Subsistemas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trae al portal las cuentas que ya existen en Dashboard y Tickets para poder activar el inicio único sin dejar a
 * nadie fuera. Por defecto es SIMULACIÓN (no escribe nada); con --ejecutar crea la cuenta activa en el portal con su
 * acceso (marcado "cuenta existente": la persona lo vincula con su contraseña de ese sistema la primera vez que entra)
 * y, con --avisar, le manda un correo con las instrucciones. Nunca toca contraseñas ni cuentas del subsistema.
 */
class MigrateAccountsCommand extends Command
{
    protected $signature = 'portal:migrar-cuentas
        {--ejecutar : Crea las cuentas (sin esto solo reporta)}
        {--avisar : Con --ejecutar, envía a cada persona el correo con las instrucciones}
        {--solo= : Limita a un correo (para probar con una sola cuenta)}';

    protected $description = 'Crea en el portal las cuentas que solo existen en Dashboard o Tickets';

    public function handle(SubsystemGateway $gateway): int
    {
        $existing = User::query()->pluck('email')->map(fn ($e): string => mb_strtolower((string) $e))->flip();
        $only = $this->option('solo') ? mb_strtolower((string) $this->option('solo')) : null;

        /** @var array<string, array{nombre: string, accesos: array<string, string>}> $plan */
        $plan = [];
        $skipped = [];

        foreach (Subsistemas::claves() as $key) {
            $accounts = $gateway->accounts($key);

            if ($accounts === null) {
                $this->error("{$key}: no responde o el puente no está configurado. No se cambió nada.");

                return self::FAILURE;
            }

            foreach ($accounts as $account) {
                $email = mb_strtolower((string) ($account['email'] ?? ''));

                if ($email === '' || $existing->has($email) || ($only !== null && $email !== $only)) {
                    continue;
                }

                $role = $this->roleKey($key, (string) ($account['rol'] ?? ''));

                if ($role === null) {
                    $skipped[] = "{$email} ({$key}: rol «".($account['rol'] ?? '—').'» no reconocido)';

                    continue;
                }

                $plan[$email]['nombre'] ??= (string) ($account['nombre'] ?? $email);
                $plan[$email]['accesos'][$key] = $role;
            }
        }

        $this->report($plan, $skipped);

        if (! $this->option('ejecutar')) {
            $this->newLine();
            $this->line('Simulación: no se cambió nada. Usa --ejecutar para crear las cuentas (y --avisar para enviar el correo).');

            return self::SUCCESS;
        }

        foreach ($plan as $email => $data) {
            $user = DB::transaction(fn (): User => $this->create($email, $data));

            if ($this->option('avisar')) {
                $user->notify(new AccountMigrated);
            }
        }

        $this->info(count($plan).' cuentas creadas'.($this->option('avisar') ? ' y avisadas.' : ' (sin aviso).'));

        return self::SUCCESS;
    }

    /** @param  array{nombre: string, accesos: array<string, string>}  $data */
    private function create(string $email, array $data): User
    {
        $user = new User(['name' => $data['nombre'], 'email' => $email, 'password' => Str::random(48)]);
        $user->forceFill(['status' => UserStatus::Active, 'portal_role' => PortalRole::Usuario, 'decided_at' => now()])->save();

        foreach ($data['accesos'] as $key => $role) {
            $access = $user->accesos()->create(['subsistema' => $key, 'rol' => $role, 'contexto' => []]);
            // La cuenta ya existe allá: no se vuelve a crear; se vincula con su contraseña al entrar la primera vez.
            $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::CONFLICTO, 'intentos' => 0])->save();
        }

        activity('auth')->performedOn($user)->event('migrated')
            ->withProperties(['accesos' => $data['accesos']])->log('migrated');

        return $user;
    }

    /** Clave de rol del portal a partir del nombre del rol en el subsistema ("Jefe de Zona" → jefe_zona). */
    private function roleKey(string $subsystem, string $names): ?string
    {
        $valid = Subsistemas::rolesDe($subsystem);

        foreach (explode(',', $names) as $name) {
            $key = (string) Str::of($name)->ascii()->lower()->replace(' de ', ' ')->trim()->snake();

            if (in_array($key, $valid, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{nombre: string, accesos: array<string, string>}>  $plan
     * @param  list<string>  $skipped
     */
    private function report(array $plan, array $skipped): void
    {
        $this->info(count($plan).' cuentas por crear en el portal:');
        $this->table(
            ['Correo', 'Nombre', 'Accesos (sistema: rol)'],
            collect($plan)->map(fn (array $d, string $email): array => [
                $email, $d['nombre'], collect($d['accesos'])->map(fn ($r, $k): string => "{$k}: {$r}")->implode(', '),
            ])->values()->all(),
        );

        if ($skipped !== []) {
            $this->warn('Omitidas (revisar a mano):');
            foreach ($skipped as $line) {
                $this->line(' - '.$line);
            }
        }
    }
}
