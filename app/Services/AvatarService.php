<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Jobs\PushAvatar;
use App\Models\User;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto de perfil única del ecosistema. El archivo recibido NUNCA se guarda tal cual: se decodifica con GD, se recorta
 * al centro en un cuadrado y se vuelve a codificar como JPEG (sin EXIF/GPS ni contenido incrustado). Lo que se sirve
 * y lo que se reparte a los subsistemas es siempre esa imagen generada aquí.
 */
final class AvatarService
{
    /** Guarda la foto desde bytes JPG/PNG. `$propagate` false cuando el cambio vino de un subsistema (evita el eco). */
    public function setFromBytes(User $user, string $bytes, bool $propagate = true, ?string $origin = null): User
    {
        $jpeg = $this->normalize($bytes);
        $disk = $this->disk();
        $path = (string) config('ecosistema.avatares.directorio').'/'.Str::random(40).'.jpg';
        $previous = $user->avatar_path;

        if (! $disk->put($path, $jpeg)) {
            throw new BusinessRuleException(__('portal.avatar.upload_failed'));
        }

        $user->forceFill(['avatar_path' => $path])->save();
        activity('auth')->performedOn($user)->causedBy($user)->event('avatar_updated')
            ->withProperties(['origen' => $origin ?? 'portal'])->log('avatar_updated');

        if ($previous !== null) {
            $disk->delete($previous);
        }

        if ($propagate) {
            PushAvatar::dispatch($user->id, $origin);
        }

        return $user;
    }

    public function remove(User $user, bool $propagate = true, ?string $origin = null): User
    {
        $previous = $user->avatar_path;

        if ($previous === null) {
            return $user;
        }

        $user->forceFill(['avatar_path' => null])->save();
        activity('auth')->performedOn($user)->causedBy($user)->event('avatar_removed')
            ->withProperties(['origen' => $origin ?? 'portal'])->log('avatar_removed');
        $this->disk()->delete($previous);

        if ($propagate) {
            PushAvatar::dispatch($user->id, $origin);
        }

        return $user;
    }

    /** Bytes del JPEG actual o null. */
    public function bytes(User $user): ?string
    {
        if ($user->avatar_path === null || ! $this->disk()->exists($user->avatar_path)) {
            return null;
        }

        return (string) $this->disk()->get($user->avatar_path);
    }

    private function normalize(string $bytes): string
    {
        $info = @getimagesizefromstring($bytes);
        $max = (int) config('ecosistema.avatares.max_dimension');

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new BusinessRuleException(__('portal.avatar.type'));
        }

        // Se revisan las dimensiones ANTES de decodificar: una imagen pequeña en bytes puede ocupar gigas en memoria.
        if ($info[0] < 32 || $info[1] < 32 || $info[0] > $max || $info[1] > $max) {
            throw new BusinessRuleException(__('portal.avatar.dimensions', ['max' => $max]));
        }

        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            throw new BusinessRuleException(__('portal.avatar.type'));
        }

        $size = (int) config('ecosistema.avatares.tamano');
        $side = min(imagesx($source), imagesy($source));
        $target = imagecreatetruecolor($size, $size);
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($target, null, 85);

        return (string) ob_get_clean();
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
