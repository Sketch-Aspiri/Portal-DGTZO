<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Silenciado del push de un sistema para una persona (sin fila = activo). */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'subsistema', 'muted'];

    protected function casts(): array
    {
        return ['muted' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
