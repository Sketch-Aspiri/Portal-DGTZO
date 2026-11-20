<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Foto cambiada en un subsistema (puente interno, firmado con el secreto de ese subsistema). La foto queda en el
 * portal y se reparte a los DEMÁS subsistemas; el que la originó no la recibe de vuelta.
 */
class SubsystemAvatarController extends Controller
{
    public function update(Request $request, AvatarService $avatars): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'], 'imagen' => ['required', 'string']]);

        if (strlen($data['imagen']) > (int) config('ecosistema.avatares.max_bytes_puente') * 4 / 3 + 8) {
            return response()->json(['error' => 'too_large'], 422);
        }

        $bytes = base64_decode($data['imagen'], true);
        $user = $this->account($data['email']);

        if ($user === null) {
            return response()->json(['estado' => 'sin_cuenta'], 404);
        }

        if ($bytes === false) {
            return response()->json(['error' => 'invalid'], 422);
        }

        try {
            $avatars->setFromBytes($user, $bytes, true, (string) $request->attributes->get('bridge_system'));
        } catch (BusinessRuleException) {
            return response()->json(['error' => 'invalid_image'], 422);
        }

        return response()->json(['estado' => 'ok']);
    }

    public function destroy(Request $request, AvatarService $avatars): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $user = $this->account($data['email']);

        if ($user === null) {
            return response()->json(['estado' => 'sin_cuenta'], 404);
        }

        $avatars->remove($user, true, (string) $request->attributes->get('bridge_system'));

        return response()->json(['estado' => 'ok']);
    }

    private function account(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
    }
}
