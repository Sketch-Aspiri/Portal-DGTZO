<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CalendarioTipo;
use App\Models\CalendarioEvento;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Carga la lista oficial de días inhábiles desde un archivo de texto (una línea por día: `YYYY-MM-DD;Nombre`).
 * Es idempotente (no duplica una fecha ya cargada) y no avisa a nadie: la lista no se inventa, la aporta la Zona.
 */
class CalendarioCargarInhabilesCommand extends Command
{
    protected $signature = 'calendario:cargar-inhabiles {archivo : Ruta del archivo} {--simular : Solo muestra lo que haría}';

    protected $description = 'Carga días inhábiles oficiales desde un archivo (YYYY-MM-DD;Nombre)';

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');

        if (! is_file($ruta) || ! is_readable($ruta)) {
            $this->error('No se puede leer el archivo.');

            return self::FAILURE;
        }

        $zona = config('app.display_timezone');
        $nuevos = 0;
        $existentes = 0;
        $errores = 0;

        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $numero => $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            [$fecha, $nombre] = array_pad(array_map('trim', explode(';', $linea, 2)), 2, '');
            $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha, $zona);

            if ($dia === false || $dia->format('Y-m-d') !== $fecha || $nombre === '' || mb_strlen($nombre) > 120) {
                $this->warn('Línea '.($numero + 1).' no válida: '.$linea);
                $errores++;

                continue;
            }

            $inicia = $dia->startOfDay()->utc();

            if (CalendarioEvento::query()->where('tipo', CalendarioTipo::Inhabil->value)->where('inicia_at', $inicia)->exists()) {
                $existentes++;

                continue;
            }

            if (! $this->option('simular')) {
                CalendarioEvento::create([
                    'tipo' => CalendarioTipo::Inhabil->value,
                    'titulo' => $nombre,
                    'todo_el_dia' => true,
                    'inicia_at' => $inicia,
                    'termina_at' => $dia->endOfDay()->utc(),
                ]);
            }

            $nuevos++;
        }

        $this->info("Nuevos: {$nuevos}. Ya existían: {$existentes}. Con error: {$errores}.".($this->option('simular') ? ' (simulación)' : ''));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
