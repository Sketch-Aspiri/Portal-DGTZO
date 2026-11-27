<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarioTipo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entrada del calendario oficial de la Zona Oriente. Los instantes se guardan en UTC y se muestran en la zona de la
 * persona (`app.display_timezone`, Cancún). Solo quien administra el portal las crea o edita.
 */
class CalendarioEvento extends Model
{
    use SoftDeletes;

    protected $table = 'calendario_eventos';

    protected $fillable = [
        'tipo', 'titulo', 'descripcion', 'lugar', 'inicia_at', 'termina_at', 'todo_el_dia',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => CalendarioTipo::class,
            'inicia_at' => 'datetime',
            'termina_at' => 'datetime',
            'todo_el_dia' => 'boolean',
            'avisado_at' => 'datetime',
            'recordado_at' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Entradas que tocan el rango [desde, hasta) (instantes UTC). */
    public function scopeEnRango(Builder $query, CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        return $query->where('inicia_at', '<', $hasta)
            ->where(fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('termina_at')->where('inicia_at', '>=', $desde))
                ->orWhere('termina_at', '>=', $desde));
    }

    public function etiqueta(): string
    {
        return $this->tipo->label();
    }

    public function chip(): string
    {
        return $this->tipo->chip();
    }

    public function simbolo(): string
    {
        return $this->tipo->simbolo();
    }

    public function editable(): bool
    {
        return true;
    }

    public function inicioLocal(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->inicia_at)->setTimezone(config('app.display_timezone'));
    }

    public function finLocal(): ?CarbonImmutable
    {
        return $this->termina_at === null ? null : CarbonImmutable::instance($this->termina_at)->setTimezone(config('app.display_timezone'));
    }

    /**
     * Días locales (Y-m-d) que cubre la entrada. Una entrada de día completo termina a las 23:59 del último día.
     *
     * @return list<string>
     */
    public function diasLocales(): array
    {
        $inicio = $this->inicioLocal()->startOfDay();
        $fin = ($this->finLocal() ?? $this->inicioLocal())->startOfDay();
        $dias = [];

        for ($dia = $inicio; $dia <= $fin && count($dias) < 366; $dia = $dia->addDay()) {
            $dias[] = $dia->format('Y-m-d');
        }

        return $dias;
    }
}
