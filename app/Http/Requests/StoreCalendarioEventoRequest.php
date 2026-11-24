<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CalendarioTipo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Captura de una entrada del calendario: fechas y horas en la zona de la persona (Cancún); `atributos()` las
 * convierte a los instantes UTC que se guardan. Los días inhábiles y las entradas «todo el día» no llevan hora.
 */
class StoreCalendarioEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-calendar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(CalendarioTipo::class)],
            'titulo' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'lugar' => ['nullable', 'string', 'max:120'],
            'todo_el_dia' => ['nullable', 'boolean'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->diaCompleto() && $this->input('hora_inicio') === null) {
                $validator->errors()->add('hora_inicio', __('portal.calendar.errors.start_time'));

                return;
            }

            $atributos = $this->atributos();

            if ($atributos['termina_at'] !== null && $atributos['termina_at'] < $atributos['inicia_at']) {
                $validator->errors()->add('hora_fin', __('portal.calendar.errors.end_before_start'));
            }
        }];
    }

    /**
     * @return array<string, mixed> Atributos de `CalendarioEvento` con los instantes en UTC.
     */
    public function atributos(): array
    {
        $zona = config('app.display_timezone');
        $diaCompleto = $this->diaCompleto();
        $fechaInicio = (string) $this->input('fecha_inicio');
        $fechaFin = (string) ($this->input('fecha_fin') ?: $fechaInicio);

        if ($diaCompleto) {
            $inicia = CarbonImmutable::parse($fechaInicio, $zona)->startOfDay();
            $termina = CarbonImmutable::parse($fechaFin, $zona)->endOfDay();
        } else {
            $inicia = CarbonImmutable::parse($fechaInicio.' '.$this->input('hora_inicio'), $zona);
            $termina = $this->input('hora_fin') === null
                ? null
                : CarbonImmutable::parse($fechaFin.' '.$this->input('hora_fin'), $zona);
        }

        return [
            'tipo' => $this->input('tipo'),
            'titulo' => trim((string) $this->input('titulo')),
            'descripcion' => $this->filled('descripcion') ? trim((string) $this->input('descripcion')) : null,
            'lugar' => $this->filled('lugar') ? trim((string) $this->input('lugar')) : null,
            'todo_el_dia' => $diaCompleto,
            'inicia_at' => $inicia->utc(),
            'termina_at' => $termina?->utc(),
        ];
    }

    private function diaCompleto(): bool
    {
        return $this->boolean('todo_el_dia') || $this->input('tipo') === CalendarioTipo::Inhabil->value;
    }
}
