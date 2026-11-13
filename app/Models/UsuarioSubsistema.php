<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioSubsistema extends Model
{
    public const PENDIENTE = 'pendiente';

    public const OK = 'ok';

    /** Ya existía una cuenta con ese correo en el subsistema: se vincula con su contraseña al entrar (no se reintenta). */
    public const CONFLICTO = 'conflicto';

    public const ERROR = 'error';

    protected $table = 'usuario_subsistema';

    protected $fillable = ['user_id', 'subsistema', 'rol', 'contexto'];

    protected function casts(): array
    {
        return ['aprovisionado_at' => 'datetime', 'contexto' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
