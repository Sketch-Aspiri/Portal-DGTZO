<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que una cuenta pendiente declara al registrarse: quién es, qué hace y qué sistemas, equipos y estación pide.
 * Es solo una sugerencia: quien aprueba la revisa, la corrige y decide (ApproveUserRequest vuelve a validar todo).
 */
class SolicitudAcceso extends Model
{
    public const ENVIADA = 'enviada';

    public const APROBADA = 'aprobada';

    public const RECHAZADA = 'rechazada';

    protected $table = 'solicitudes_acceso';

    /** @var list<string> */
    protected $fillable = ['puesto', 'ubicacion', 'telefono', 'funciones', 'justificacion', 'accesos'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['accesos' => 'array', 'enviada_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
