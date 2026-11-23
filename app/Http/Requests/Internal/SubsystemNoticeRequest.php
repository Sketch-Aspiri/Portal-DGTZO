<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\Services\Ecosystem\NotificationHubService;
use App\Support\EcosystemUrl;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /interno/subsistemas/notificaciones`: aviso de un sistema para personas del ecosistema. Ya pasó por
 * `VerifySubsystemBridge` (firma, fecha y nonce); aquí se autoriza que lo mande un sistema que emite avisos y se
 * validan los límites: hasta 50 correos, título <= 60, cuerpo <= 120 (se ven en la pantalla bloqueada) y una URL del
 * ecosistema. No hay usuario de sesión: el alcance lo pone NotificationHubService.
 */
class SubsystemNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->attributes->get('bridge_system'), NotificationHubService::SYSTEMS, true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'correos' => ['required', 'array', 'min:1', 'max:50'],
            'correos.*' => ['required', 'string', 'email:rfc', 'max:255'],
            // Sin control ni formato Unicode (bidi, ancho cero, separadores de línea): se leen en la pantalla bloqueada.
            'titulo' => ['required', 'string', 'max:60', 'regex:/^[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+$/u'],
            'cuerpo' => ['required', 'string', 'max:120', 'regex:/^[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+$/u'],
            'url' => ['required', 'string', 'max:300', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || EcosystemUrl::normalize($value) === null) {
                    $fail('invalid');
                }
            }],
            'ref' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/D'],
            'modulo' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/D'],
        ];
    }

    public function normalizedUrl(): string
    {
        return (string) EcosystemUrl::normalize((string) $this->validated('url'));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['error' => 'invalid'], 422));
    }
}
