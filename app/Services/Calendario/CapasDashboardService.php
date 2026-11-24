<?php

declare(strict_types=1);

namespace App\Services\Calendario;

use App\Services\Ecosystem\BridgeClient;
use App\Support\CapaEntrada;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Cumpleaños y vacaciones del personal, leídos del Dashboard por el puente firmado (solo lectura). Si el Dashboard
 * no responde o contesta algo inesperado, la capa queda vacía y el calendario sigue funcionando; la falla no se guarda
 * en caché más que unos segundos para no golpear al Dashboard en cada recarga.
 */
final class CapasDashboardService
{
    public function __construct(private readonly BridgeClient $puente) {}

    public function activa(): bool
    {
        return (bool) config('calendario.capas_dashboard') && $this->puente->configured('dashboard');
    }

    /** @return list<CapaEntrada> */
    public function cumpleanos(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = $this->consultar('cumpleanos', $desde, $hasta);

        return array_values(array_filter(array_map(function (array $f): ?CapaEntrada {
            return $this->fecha($f['fecha'] ?? null) === null || ! is_string($f['nombre'] ?? null)
                ? null
                : new CapaEntrada('cumpleanos', $f['nombre'], [$f['fecha']]);
        }, $filas)));
    }

    /** @return list<CapaEntrada> */
    public function vacaciones(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = $this->consultar('vacaciones', $desde, $hasta);
        $salida = [];

        foreach ($filas as $f) {
            $inicio = $this->fecha($f['inicio'] ?? null);
            $fin = $this->fecha($f['fin'] ?? null);

            if ($inicio === null || $fin === null || $fin < $inicio || ! is_string($f['nombre'] ?? null)) {
                continue;
            }

            $dias = [];
            for ($dia = max($inicio, $desde); $dia <= min($fin, $hasta); $dia = $dia->addDay()) {
                $dias[] = $dia->format('Y-m-d');
            }

            if ($dias !== []) {
                $salida[] = new CapaEntrada('vacaciones', $f['nombre'], $dias);
            }
        }

        return $salida;
    }

    /** @return list<array<string, mixed>> */
    private function consultar(string $capa, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        if (! $this->activa()) {
            return [];
        }

        $clave = 'calendario:capa:'.$capa.':'.$desde->format('Y-m-d').':'.$hasta->format('Y-m-d');
        $enCache = Cache::get($clave);

        if (is_array($enCache)) {
            return $enCache;
        }

        $respuesta = $this->puente->send('dashboard', 'GET', '/interno/portal/calendario/'.$capa, [
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
        ]);
        $filas = $respuesta?->successful() ? $respuesta->json($capa) : null;

        if (! is_array($filas)) {
            Cache::put($clave, [], 30);

            return [];
        }

        $filas = array_values(array_filter($filas, 'is_array'));
        Cache::put($clave, $filas, (int) config('calendario.cache_capas', 600));

        return $filas;
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor) !== 1) {
            return null;
        }

        $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }
}
