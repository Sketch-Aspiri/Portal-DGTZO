<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA TOTP. El secreto se guarda cifrado (cast `encrypted`), los códigos de recuperación hasheados
 * y cada código TOTP solo puede usarse una vez (mismo diseño que el sistema de Tickets).
 */
final class TwoFactorService
{
    /** Clave de sesión: id del usuario que ya superó el desafío en esta sesión. */
    public const SESSION_KEY = 'two_factor.verified_user_id';

    private const SECRET_LENGTH = 32;

    private const RECOVERY_CODES = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function isVerifiedInSession(User $user): bool
    {
        return session(self::SESSION_KEY) === $user->getKey();
    }

    public function markVerifiedInSession(User $user): void
    {
        session()->regenerate();
        session()->put(self::SESSION_KEY, $user->getKey());
    }

    public function clearSessionVerification(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Genera (o reutiliza mientras no se confirme) el secreto y devuelve lo necesario para mostrarlo.
     *
     * @return array{secret: string, qr_svg: string}|null null si ya está activado
     */
    public function prepareSetup(User $user): ?array
    {
        $secret = $this->withLockedUser($user, function (User $locked): ?string {
            if ($locked->hasTwoFactorEnabled()) {
                return null;
            }

            if ($locked->two_factor_secret === null) {
                $locked->forceFill(['two_factor_secret' => $this->google2fa->generateSecretKey(self::SECRET_LENGTH)])->save();
            }

            return $locked->two_factor_secret;
        });

        if ($secret === null) {
            return null;
        }

        $uri = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return ['secret' => $secret, 'qr_svg' => $this->renderQrSvg($uri)];
    }

    /**
     * Confirma la configuración con un código válido y devuelve los códigos de recuperación (una sola vez).
     *
     * @return list<string>|null null si el código es incorrecto o no hay configuración pendiente
     */
    public function confirmSetup(User $user, string $code): ?array
    {
        return $this->withLockedUser($user, function (User $locked) use ($code): ?array {
            if ($locked->two_factor_secret === null || $locked->hasTwoFactorEnabled()) {
                return null;
            }

            $timestamp = $this->verifyCode($locked, $code);

            if ($timestamp === null) {
                activity('auth')->performedOn($locked)->causedBy($locked)->event('two_factor_setup_failed')->log('two_factor_setup_failed');

                return null;
            }

            $plain = $this->generateRecoveryCodes();

            $locked->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_timestamp' => $timestamp,
                'two_factor_recovery_codes' => $this->hashCodes($plain),
            ])->save();

            activity('auth')->performedOn($locked)->causedBy($locked)->event('two_factor_enabled')->log('two_factor_enabled');

            return $plain;
        });
    }

    /** Valida el código TOTP del desafío (rechaza códigos ya usados), bajo bloqueo de fila. */
    public function passesChallenge(User $user, string $code): bool
    {
        return $this->withLockedUser($user, function (User $locked) use ($code): bool {
            if (! $locked->hasTwoFactorEnabled()) {
                return false;
            }

            $timestamp = $this->verifyCode($locked, $code);

            if ($timestamp === null) {
                activity('auth')->performedOn($locked)->causedBy($locked)->event('two_factor_challenge_failed')->log('two_factor_challenge_failed');

                return false;
            }

            $locked->forceFill(['two_factor_last_timestamp' => $timestamp])->save();

            return true;
        });
    }

    /** Consume un código de recuperación (uso único), bajo bloqueo de fila. */
    public function passesRecoveryChallenge(User $user, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);

        return $this->withLockedUser($user, function (User $locked) use ($normalized): bool {
            $hashes = $locked->two_factor_recovery_codes ?? [];

            foreach ($hashes as $index => $hash) {
                if (Hash::check($normalized, $hash)) {
                    unset($hashes[$index]);
                    $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                    activity('auth')->performedOn($locked)->causedBy($locked)->event('two_factor_recovery_used')
                        ->withProperties(['remaining' => count($hashes)])->log('two_factor_recovery_used');

                    return true;
                }
            }

            activity('auth')->performedOn($locked)->causedBy($locked)->event('two_factor_challenge_failed')->log('two_factor_challenge_failed');

            return false;
        });
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * @template TResult
     *
     * @param  Closure(User): TResult  $callback
     * @return TResult
     */
    private function withLockedUser(User $user, Closure $callback): mixed
    {
        $locked = null;

        $result = DB::transaction(function () use ($user, $callback, &$locked): mixed {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            return $callback($locked);
        });

        $user->setRawAttributes($locked->getAttributes(), true);

        return $result;
    }

    /** @return int|null periodo TOTP aceptado, o null si no es válido / ya se usó */
    private function verifyCode(User $user, string $code): ?int
    {
        $result = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            preg_replace('/\s+/', '', $code) ?? '',
            // 0 (no null): así google2fa devuelve el periodo aceptado y no solo `true`.
            $user->two_factor_last_timestamp ?? 0,
        );

        return $result === false ? null : (int) $result;
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        return array_map(
            fn (): string => Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5)),
            range(1, self::RECOVERY_CODES),
        );
    }

    /**
     * @param  list<string>  $plain
     * @return list<string>
     */
    private function hashCodes(array $plain): array
    {
        return array_map(fn (string $code): string => Hash::make($this->normalizeRecoveryCode($code)), $plain);
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return Str::lower(str_replace(['-', ' '], '', trim($code)));
    }

    private function renderQrSvg(string $uri): string
    {
        $renderer = new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd);

        // Se elimina la declaración XML para incrustar el SVG en HTML.
        return Str::after((new Writer($renderer))->writeString($uri), '?>');
    }
}
