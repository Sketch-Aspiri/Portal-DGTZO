<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Support\Subsistemas;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    // status, rol y 2FA nunca se asignan en masa: solo desde los servicios.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'two_factor_last_timestamp', 'avatar_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->portal_user_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'portal_role' => PortalRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function solicitudAcceso(): HasOne
    {
        return $this->hasOne(SolicitudAcceso::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** ¿Silenció el push de este sistema? (el aviso igual queda en su centro de notificaciones). */
    public function mutesPush(string $subsistema): bool
    {
        return $this->notificationPreferences()->where('subsistema', $subsistema)->where('muted', true)->exists();
    }

    /** Accesos vigentes (los quitados desde el portal no cuentan: ni lanzador, ni SSO, ni avisos). */
    public function accesos(): HasMany
    {
        return $this->hasMany(UsuarioSubsistema::class)->whereNull('revocado_at');
    }

    /** Todos los accesos, también los quitados (para reactivarlos sin chocar con el índice único). */
    public function todosLosAccesos(): HasMany
    {
        return $this->hasMany(UsuarioSubsistema::class);
    }

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return route('avatars.show', ['user' => $this->getKey(), 'v' => substr(sha1((string) $this->avatar_path), 0, 10)]);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** El 2FA es obligatorio para quienes aprueban cuentas. */
    public function requiresTwoFactor(): bool
    {
        return $this->portal_role->canApprove();
    }

    public function canApprove(): bool
    {
        return $this->isActive() && $this->portal_role->canApprove();
    }

    /**
     * Subsistemas del catálogo a los que tiene acceso (clave => catálogo + rol asignado).
     *
     * @return array<string, array<string, mixed>>
     */
    public function subsistemasAccesibles(): array
    {
        $catalogo = Subsistemas::todos();
        $resultado = [];

        foreach ($this->accesos as $acceso) {
            if (isset($catalogo[$acceso->subsistema])) {
                $resultado[$acceso->subsistema] = $catalogo[$acceso->subsistema] + ['rol_asignado' => $acceso->rol];
            }
        }

        return $resultado;
    }

    /** Quienes aprueban cuentas: administradores y jefes de zona activos. */
    public function scopeApprovers(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value)
            ->whereIn('portal_role', [PortalRole::Administrador->value, PortalRole::JefeZona->value]);
    }
}
