<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Foto de perfil única. El archivo vive en disco privado y solo se sirve por aquí; siempre es un JPEG generado por el servidor. */
class ProfileAvatarController extends Controller
{
    public function __construct(private readonly AvatarService $avatars) {}

    public function show(User $user): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($user->avatar_path !== null && $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, 'avatar.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            // La URL lleva `v` (cambia con cada foto): se puede guardar en caché sin verse vieja.
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => [
                'required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png',
                'max:'.(int) config('ecosistema.avatares.max_kb'),
            ],
        ]);

        $this->avatars->setFromBytes($request->user(), (string) file_get_contents($request->file('avatar')->getRealPath()));

        return redirect()->route('profile.edit')->with('status', 'avatar-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->avatars->remove($request->user());

        return redirect()->route('profile.edit')->with('status', 'avatar-removed');
    }
}
