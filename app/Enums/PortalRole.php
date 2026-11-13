<?php

declare(strict_types=1);

namespace App\Enums;

/** Rol dentro del portal (quién aprueba cuentas); el rol de cada subsistema va aparte. */
enum PortalRole: string
{
    case Administrador = 'administrador';
    case JefeZona = 'jefe_zona';
    case Usuario = 'usuario';

    public function canApprove(): bool
    {
        return $this !== self::Usuario;
    }
}
