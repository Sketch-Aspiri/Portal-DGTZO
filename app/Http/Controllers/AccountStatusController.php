<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Services\Ecosystem\SubsystemGateway;
use App\Support\Subsistemas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function __construct(private readonly SubsystemGateway $gateway) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isActive()) {
            return redirect()->route('dashboard');
        }

        if ($user->status !== UserStatus::Pending) {
            return view('account.status', ['status' => $user->status, 'solicitud' => null, 'wizard' => false, 'catalogos' => []]);
        }

        $solicitud = $user->solicitudAcceso;
        $wizard = $solicitud === null || $request->boolean('editar');

        return view('account.status', [
            'status' => $user->status,
            'solicitud' => $solicitud,
            'wizard' => $wizard,
            'catalogos' => $wizard ? $this->catalogs() : [],
        ]);
    }

    /**
     * Catálogo vivo de cada subsistema, reducido a lo que la persona necesita para elegir (nombres e ids) y sin roles
     * privilegiados. null = el subsistema no respondió (el asistente pide texto libre).
     *
     * @return array<string, array<string, mixed>|null>
     */
    private function catalogs(): array
    {
        return collect(Subsistemas::claves())->mapWithKeys(function (string $key): array {
            $cat = $this->gateway->catalog($key);

            if ($cat === null) {
                return [$key => null];
            }

            return [$key => [
                'equipos' => array_map(fn (array $e): array => [
                    'id' => (int) $e['id'], 'nombre' => (string) ($e['nombre'] ?? ''),
                    'estacion' => (string) ($e['estacion'] ?? '—'), 'coordinacion' => (string) ($e['coordinacion'] ?? '—'),
                ], array_values((array) ($cat['equipos'] ?? []))),
                'coordinaciones' => array_map(fn (array $c): array => [
                    'id' => (int) $c['id'], 'nombre' => (string) ($c['nombre'] ?? ''), 'estacion' => (string) ($c['estacion'] ?? '—'),
                ], array_values((array) ($cat['coordinaciones'] ?? []))),
                'estaciones' => array_map(fn (array $s): array => [
                    'id' => (int) $s['id'], 'nombre' => (string) ($s['nombre'] ?? ''),
                ], array_values((array) ($cat['estaciones'] ?? []))),
                'areas' => array_map(fn (array $a): array => [
                    'clave' => (string) $a['clave'], 'nombre' => (string) ($a['nombre'] ?? $a['clave']),
                ], array_values((array) ($cat['areas'] ?? []))),
            ]];
        })->all();
    }
}
