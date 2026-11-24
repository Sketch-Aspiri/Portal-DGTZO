<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CalendarioTipo;
use App\Models\CalendarioEvento;
use App\Services\Calendario\CapasDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consulta del calendario oficial (todas las cuentas activas): vista mensual y lista de próximas entradas. */
class CalendarioController extends Controller
{
    public function index(Request $request, CapasDashboardService $capas): View
    {
        $zona = config('app.display_timezone');
        $hoy = CarbonImmutable::now($zona)->startOfDay();

        $mes = $this->mesSolicitado((string) $request->query('mes'), $zona) ?? $hoy->startOfMonth();
        $tipo = CalendarioTipo::tryFrom((string) $request->query('tipo'));

        // Cuadrícula de lunes a domingo que contiene el mes completo.
        $desde = $mes->startOfWeek(CarbonImmutable::MONDAY);
        $hasta = $mes->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addDay()->startOfDay();

        $entradas = CalendarioEvento::query()
            ->enRango($desde->utc(), $hasta->utc())
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo->value))
            ->orderBy('inicia_at')
            ->get();

        $porDia = [];
        foreach ($entradas as $entrada) {
            foreach ($entrada->diasLocales() as $dia) {
                $porDia[$dia][] = $entrada;
            }
        }

        // Capas del Dashboard (solo lectura), solo las que la persona activó y puede ver.
        $disponibles = $this->capasDisponibles($request, $capas);
        $activas = array_values(array_intersect(array_filter((array) $request->query('capas', []), 'is_string'), $disponibles));
        $hastaLocal = $hasta->subDay();

        $deCapas = [
            ...(in_array('cumpleanos', $activas, true) ? $capas->cumpleanos($desde, $hastaLocal) : []),
            ...(in_array('vacaciones', $activas, true) ? $capas->vacaciones($desde, $hastaLocal) : []),
        ];
        foreach ($deCapas as $entrada) {
            foreach ($entrada->dias as $dia) {
                $porDia[$dia][] = $entrada;
            }
        }
        ksort($porDia);

        $proximas = CalendarioEvento::query()
            ->where(fn ($q) => $q->where('inicia_at', '>=', $hoy->utc())->orWhere('termina_at', '>=', $hoy->utc()))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo->value))
            ->orderBy('inicia_at')
            ->limit(10)
            ->get();

        return view('calendario.index', [
            'mes' => $mes,
            'hoy' => $hoy,
            'desde' => $desde,
            'semanas' => (int) ceil($desde->diffInDays($hasta) / 7),
            'porDia' => $porDia,
            'proximas' => $proximas,
            'tipo' => $tipo,
            'capasDisponibles' => $disponibles,
            'capasActivas' => $activas,
            'previo' => $mes->subMonth()->format('Y-m'),
            'siguiente' => $mes->addMonth()->format('Y-m'),
        ]);
    }

    /** @return list<string> */
    private function capasDisponibles(Request $request, CapasDashboardService $capas): array
    {
        if (! $capas->activa()) {
            return [];
        }

        $vacaciones = config('calendario.vacaciones_para_todos') || $request->user()->can('manage-calendar');

        return $vacaciones ? ['cumpleanos', 'vacaciones'] : ['cumpleanos'];
    }

    private function mesSolicitado(string $valor, string $zona): ?CarbonImmutable
    {
        if (preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $valor) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m', $valor, $zona)->startOfMonth();
    }
}
