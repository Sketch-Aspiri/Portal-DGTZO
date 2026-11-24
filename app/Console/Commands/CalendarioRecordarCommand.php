<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Calendario\CalendarioService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Recordatorio de un día antes: avisa las entradas de mañana (hora local de Cancún), una sola vez cada una. */
class CalendarioRecordarCommand extends Command
{
    protected $signature = 'calendario:recordar {--fecha= : Día local a recordar (Y-m-d); por omisión, mañana}';

    protected $description = 'Avisa las entradas del calendario oficial que ocurren mañana';

    public function handle(CalendarioService $calendario): int
    {
        $zona = config('app.display_timezone');
        $dia = $this->option('fecha')
            ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('fecha'), $zona)
            : CarbonImmutable::now($zona)->addDay()->startOfDay();

        if ($dia === false) {
            $this->error('Fecha no válida; usa el formato Y-m-d.');

            return self::INVALID;
        }

        $this->info($calendario->recordar($dia).' recordatorio(s) enviados para '.$dia->format('Y-m-d').'.');

        return self::SUCCESS;
    }
}
