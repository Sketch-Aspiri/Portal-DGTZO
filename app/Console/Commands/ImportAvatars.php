<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\Ecosystem\BridgeClient;
use Illuminate\Console\Command;

/**
 * Hereda al portal las fotos que las personas ya tenían en un subsistema (hoy Tickets). Solo llena a quien no tiene
 * foto en el portal: nunca pisa una existente. Después de importar, la foto se reparte a los demás subsistemas.
 */
class ImportAvatars extends Command
{
    protected $signature = 'portal:importar-fotos {subsistema=tickets : Subsistema de origen} {--simular : Solo muestra qué se importaría}';

    protected $description = 'Importa al portal las fotos de perfil que ya existen en un subsistema';

    public function handle(BridgeClient $bridge, AvatarService $avatars): int
    {
        $system = (string) $this->argument('subsistema');

        if (! in_array($system, (array) config('ecosistema.avatares.subsistemas'), true) || ! $bridge->configured($system)) {
            $this->error("El subsistema «{$system}» no está configurado para fotos.");

            return self::FAILURE;
        }

        $imported = $skipped = $failed = 0;

        User::query()->whereNull('avatar_path')->whereHas('accesos', fn ($q) => $q->where('subsistema', $system))
            ->orderBy('id')->each(function (User $user) use ($bridge, $avatars, $system, &$imported, &$skipped, &$failed): void {
                $response = $bridge->send($system, 'GET', '/interno/portal/avatar', ['email' => mb_strtolower((string) $user->email)]);

                if ($response === null || ! $response->successful()) {
                    $failed++;
                    $this->warn("{$user->email}: sin respuesta del subsistema.");

                    return;
                }

                $bytes = base64_decode((string) $response->json('imagen'), true);

                if ($bytes === false || $bytes === '') {
                    $skipped++;

                    return;
                }

                if ($this->option('simular')) {
                    $this->line("{$user->email}: se importaría.");
                    $imported++;

                    return;
                }

                try {
                    $avatars->setFromBytes($user, $bytes, true, $system);
                    $imported++;
                } catch (BusinessRuleException $e) {
                    $failed++;
                    $this->warn("{$user->email}: {$e->getMessage()}");
                }
            });

        $this->info("Importadas: {$imported}. Sin foto: {$skipped}. Con error: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
