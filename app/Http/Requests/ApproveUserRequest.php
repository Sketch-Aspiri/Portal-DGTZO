<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PortalRole;
use App\Services\Ecosystem\SubsystemGateway;
use App\Support\Subsistemas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Aprobación de una cuenta: qué subsistemas, con qué rol y en dónde se ubica a la persona (equipos y coordinaciones
 * en Tickets, estación en el Dashboard). Lo que se ofrece y acepta sale del catálogo vivo de cada subsistema, no
 * de lo que mande el formulario.
 */
class ApproveUserRequest extends FormRequest
{
    /** @var array<string, array{rol: string, contexto: array<string, mixed>}> */
    private array $resolved = [];

    public function authorize(): bool
    {
        return Gate::allows('approve-accounts');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accesos' => ['required', 'array', 'min:1'],
            'accesos.*' => ['array'],
            'accesos.*.activo' => ['nullable', 'boolean'],
            'accesos.*.rol' => ['nullable', 'string', 'max:40'],
            'accesos.*.team_ids' => ['nullable', 'array', 'max:20'],
            'accesos.*.team_ids.*' => ['integer'],
            'accesos.*.coordination_ids' => ['nullable', 'array', 'max:20'],
            'accesos.*.coordination_ids.*' => ['integer'],
            'accesos.*.estacion_id' => ['nullable', 'integer'],
            'accesos.*.area' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $chosen = 0;

            $accesos = (array) $this->input('accesos', []);
            // El Dashboard se resuelve primero: su estación y área ubican también a la persona en Tickets.
            uksort($accesos, fn ($a, $b): int => ($b === 'dashboard') <=> ($a === 'dashboard'));

            foreach ($accesos as $key => $data) {
                if (! is_string($key) || Subsistemas::get($key) === null) {
                    $validator->errors()->add('accesos', __('portal.invalid_subsystem'));

                    continue;
                }

                if (! filter_var($data['activo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $chosen++;
                $this->resolveAccess($validator, $key, (array) $data);
            }

            if ($chosen === 0) {
                $validator->errors()->add('accesos', __('portal.choose_one'));
            }
        }];
    }

    /**
     * Accesos elegidos y ya validados: [subsistema => [rol, contexto]].
     *
     * @return array<string, array{rol: string, contexto: array<string, mixed>}>
     */
    public function accesos(): array
    {
        return $this->resolved;
    }

    /** @param  array<string, mixed>  $data */
    private function resolveAccess(Validator $validator, string $key, array $data): void
    {
        $role = (string) ($data['rol'] ?? '');
        $field = "accesos.$key.rol";

        if (! in_array($role, Subsistemas::rolesDe($key), true)) {
            $validator->errors()->add($field, __('portal.invalid_role'));

            return;
        }

        // Los roles con alcance amplio los otorga solo un administrador del portal, no un jefe de zona.
        $privileged = (array) (Subsistemas::get($key)['roles_privilegiados'] ?? []);

        if (in_array($role, $privileged, true) && $this->user()?->portal_role !== PortalRole::Administrador) {
            $validator->errors()->add($field, __('portal.privileged_role'));

            return;
        }

        $catalog = app(SubsystemGateway::class)->catalog($key);

        if ($catalog === null) {
            $validator->errors()->add("accesos.$key.activo", __('portal.catalog_unavailable'));

            return;
        }

        // Tickets sin equipos elegidos: se ubica en el subequipo de la estación y área del acceso al Dashboard.
        $dashboardContext = $this->resolved['dashboard']['contexto'] ?? [];
        if ($key === 'tickets' && $this->ids($data['team_ids'] ?? []) === [] && isset($dashboardContext['area'])) {
            $data['estacion'] = $dashboardContext['estacion'];
            $data['area'] = $dashboardContext['area'];
        }

        $context = $this->contextFor($validator, $key, $role, $data, $catalog);

        if ($context !== null) {
            $this->resolved[$key] = ['rol' => $role, 'contexto' => $context];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>|null null si no es válido (ya se agregó el error)
     */
    private function contextFor(Validator $validator, string $key, string $role, array $data, array $catalog): ?array
    {
        $roleInfo = collect((array) ($catalog['roles'] ?? []))->firstWhere('clave', $role);

        if ($roleInfo === null) {
            $validator->errors()->add("accesos.$key.rol", __('portal.invalid_role'));

            return null;
        }

        return match ($key) {
            'tickets' => $this->ticketsContext($validator, $roleInfo, $data, $catalog),
            'dashboard' => $this->dashboardContext($validator, $roleInfo, $data, $catalog),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $roleInfo
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>|null
     */
    private function ticketsContext(Validator $validator, array $roleInfo, array $data, array $catalog): ?array
    {
        $teams = $this->ids($data['team_ids'] ?? []);
        $coordinations = $this->ids($data['coordination_ids'] ?? []);
        $valid = true;

        // Sin equipos elegidos a mano se ubica por estación y área (el subequipo se deriva del acceso al Dashboard).
        $bySubteam = $teams === [] && ! empty($data['estacion']) && ! empty($data['area']);

        if ($bySubteam) {
            $exists = collect((array) ($catalog['equipos'] ?? []))->contains(
                fn (array $e): bool => (string) ($e['codigo_estacion'] ?? '') === (string) $data['estacion'] && ($e['area'] ?? null) === $data['area'],
            );

            if (! $exists) {
                $validator->errors()->add('accesos.tickets.team_ids', __('portal.no_subteam'));
                $valid = false;
            }
        } elseif (($roleInfo['requiere_equipo'] ?? false) === true) {
            $known = array_column((array) ($catalog['equipos'] ?? []), 'id');

            if ($teams === [] || array_diff($teams, $known) !== []) {
                $validator->errors()->add('accesos.tickets.team_ids', __('portal.choose_team'));
                $valid = false;
            }
        } else {
            $teams = [];
        }

        if (($roleInfo['requiere_coordinaciones'] ?? false) === true) {
            $known = array_column((array) ($catalog['coordinaciones'] ?? []), 'id');

            if ($coordinations === [] || array_diff($coordinations, $known) !== []) {
                $validator->errors()->add('accesos.tickets.coordination_ids', __('portal.choose_coordination'));
                $valid = false;
            }
        } else {
            $coordinations = [];
        }

        if (! $valid) {
            return null;
        }

        return ['team_ids' => $teams, 'coordination_ids' => $coordinations]
            + ($bySubteam ? ['estacion' => (string) $data['estacion'], 'area' => (string) $data['area']] : []);
    }

    /**
     * @param  array<string, mixed>  $roleInfo
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>|null
     */
    private function dashboardContext(Validator $validator, array $roleInfo, array $data, array $catalog): ?array
    {
        if (($roleInfo['requiere_estacion'] ?? false) !== true) {
            return ['estacion_id' => null];
        }

        $station = (int) ($data['estacion_id'] ?? 0);
        $stationInfo = collect((array) ($catalog['estaciones'] ?? []))->firstWhere('id', $station);

        if ($stationInfo === null) {
            $validator->errors()->add('accesos.dashboard.estacion_id', __('portal.choose_station'));

            return null;
        }

        $context = ['estacion_id' => $station, 'estacion' => (string) ($stationInfo['codigo'] ?? '')];

        // El área solo se pide si el Dashboard la ofrece (rol con `requiere_area`): un Dashboard viejo sigue sirviendo.
        if (($roleInfo['requiere_area'] ?? false) === true) {
            $area = (string) ($data['area'] ?? '');

            if (! in_array($area, array_column((array) ($catalog['areas'] ?? []), 'clave'), true)) {
                $validator->errors()->add('accesos.dashboard.area', __('portal.choose_area'));

                return null;
            }

            $context['area'] = $area;
        }

        return $context;
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $values): array
    {
        return array_values(array_unique(array_map('intval', is_array($values) ? $values : [])));
    }
}
