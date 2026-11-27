<?php

declare(strict_types=1);

namespace App\Enums;

/** Clases de entrada del calendario oficial (cada una con su color en la vista). */
enum CalendarioTipo: string
{
    case Evento = 'evento';
    case Inhabil = 'inhabil';
    case FechaLimite = 'fecha_limite';

    public function label(): string
    {
        return __('portal.calendar.types.'.$this->value);
    }

    /** Clases Tailwind de la etiqueta/chip (fondo y texto). */
    public function chip(): string
    {
        return match ($this) {
            self::Evento => 'bg-brand-mist text-brand-teal',
            self::Inhabil => 'bg-red-100 text-red-800',
            self::FechaLimite => 'bg-amber-100 text-amber-800',
        };
    }

    /** Figura que distingue el tipo en la cuadrícula sin depender del color (la leyenda la explica). */
    public function simbolo(): string
    {
        return match ($this) {
            self::Evento => '●',
            self::Inhabil => '■',
            self::FechaLimite => '▲',
        };
    }

    /** Los días inhábiles son de día completo por naturaleza. */
    public function esDiaCompleto(): bool
    {
        return $this === self::Inhabil;
    }
}
