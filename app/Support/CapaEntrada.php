<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Entrada de solo lectura de una capa del calendario (cumpleaños o vacaciones del Dashboard). No se edita aquí ni
 * genera avisos; se dibuja con los mismos métodos que `CalendarioEvento`.
 */
final readonly class CapaEntrada
{
    /**
     * @param  'cumpleanos'|'vacaciones'  $capa
     * @param  list<string>  $dias  Días locales (Y-m-d) que cubre.
     */
    public function __construct(
        public string $capa,
        public string $titulo,
        public array $dias,
    ) {}

    public function etiqueta(): string
    {
        return __('portal.calendar.layers.'.$this->capa);
    }

    public function chip(): string
    {
        return $this->capa === 'cumpleanos' ? 'bg-pink-100 text-pink-800' : 'bg-sky-100 text-sky-800';
    }

    public function editable(): bool
    {
        return false;
    }
}
