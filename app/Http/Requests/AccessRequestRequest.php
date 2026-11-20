<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Services\Ecosystem\SubsystemGateway;
use App\Support\Subsistemas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Solicitud de acceso de una cuenta pendiente. Solo puede pedir roles NO privilegiados y solo equipos, coordinaciones
 * y estaciones que el catálogo vivo de cada subsistema ofrece; el aprobador vuelve a validar todo al decidir.
 */
class AccessRequestRequest extends FormRequest
{
    /** @var array<string, array<string, mixed>> */
    private array $resolved = [];

    public function authorize(): bool
    {
        return $this->user()?->status === UserStatus::Pending;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'puesto' => ['required', 'string', 'min:3', 'max:120'],
            'ubicacion' => ['required', 'string', 'min:2', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'funciones' => ['required', 'string', 'min:10', 'max:1500'],
            'justificacion' => ['nullable', 'string', 'max:1000'],
            'accesos' => ['required', 'array'],
            'accesos.*' => ['array'],
            'accesos.*.activo' => ['nullable', 'boolean'],
            'accesos.*.rol' => ['nullable', 'string', 'max:40'],
            'accesos.*.team_ids' => ['nullable', 'array', 'max:20'],
            'accesos.*.team_ids.*' => ['integer'],
            'accesos.*.coordination_ids' => ['nullable', 'array', 'max:20'],
            'accesos.*.coordination_ids.*' => ['integer'],
            'accesos.*.estacion_id' => ['nullable', 'integer'],
            'accesos.*.area' => ['nullable', 'string', 'max:32'],
            'accesos.*.detalle' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('accesos', []) as $key => $data) {
                if (! is_string($key) || Subsistemas::get($key) === null) {
                    $validator->errors()->add('accesos', __('portal.invalid_subsystem'));

                    continue;
                }

                if (filter_var($data['activo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $this->resolve($validator, $key, (array) $data);
                }
            }

            if ($this->resolved === [] && ! $validator->errors()->has('accesos')) {
                $validator->errors()->add('accesos', __('portal.choose_one'));
            }
        }];
    }

    /** @return array<string, array<string, mixed>> */
    public function accesos(): array
    {
        return $this->resolved;
    }

    /** @param  array<string, mixed>  $data */
    private function resolve(Validator $validator, string $key, array $data): void
    {
        $role = (string) ($data['rol'] ?? '');
        $allowed = array_diff(Subsistemas::rolesDe($key), (array) (Subsistemas::get($key)['roles_privilegiados'] ?? []));

        if (! in_array($role, $allowed, true)) {
            $validator->errors()->add("accesos.$key.rol", __('portal.invalid_role'));

            return;
        }

        $catalog = app(SubsystemGateway::class)->catalog($key);
        $teams = $this->ids($data['team_ids'] ?? []);
        $coordinations = $this->ids($data['coordination_ids'] ?? []);
        $station = (int) ($data['estacion_id'] ?? 0);
        $area = (string) ($data['area'] ?? '');

        // Con el catálogo caído se acepta la solicitud con texto libre (`detalle`) y el aprobador completa los ids.
        if ($catalog !== null) {
            if (array_diff($teams, array_column((array) ($catalog['equipos'] ?? []), 'id')) !== []) {
                $validator->errors()->add("accesos.$key.team_ids", __('portal.choose_team'));
            }

            if (array_diff($coordinations, array_column((array) ($catalog['coordinaciones'] ?? []), 'id')) !== []) {
                $validator->errors()->add("accesos.$key.coordination_ids", __('portal.choose_coordination'));
            }

            if ($station !== 0 && ! in_array($station, array_column((array) ($catalog['estaciones'] ?? []), 'id'), true)) {
                $validator->errors()->add("accesos.$key.estacion_id", __('portal.choose_station'));
            }

            if ($area !== '' && ! in_array($area, array_column((array) ($catalog['areas'] ?? []), 'clave'), true)) {
                $validator->errors()->add("accesos.$key.area", __('portal.choose_area'));
            }
        }

        $this->resolved[$key] = array_filter([
            'activo' => true,
            'rol' => $role,
            'team_ids' => $teams,
            'coordination_ids' => $coordinations,
            'estacion_id' => $station ?: null,
            'area' => $area ?: null,
            'detalle' => trim((string) ($data['detalle'] ?? '')) ?: null,
        ], fn (mixed $v): bool => $v !== null && $v !== []);
    }

    /** @return list<int> */
    private function ids(mixed $values): array
    {
        return array_values(array_unique(array_map('intval', is_array($values) ? $values : [])));
    }
}
