<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Ecosystem\BridgeClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege /interno/subsistemas/*: llamadas de servidor a servidor de un subsistema al portal, firmadas con el secreto
 * de ese subsistema (el mismo del puente del portal hacia él; el campo `sistema` de la firma separa los sentidos).
 * Sin secreto configurado no existen (404); los errores no dicen qué condición falló.
 */
class VerifySubsystemBridge
{
    public function __construct(private readonly BridgeClient $bridge) {}

    public function handle(Request $request, Closure $next): Response
    {
        $system = (string) $request->header('X-Puente-Sistema', '');

        if (! array_key_exists($system, (array) config('ecosistema.puente.secretos')) || ! $this->bridge->configured($system)) {
            abort(404);
        }

        if (! $this->bridge->verifyIncoming($request, $system)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $request->attributes->set('bridge_system', $system);

        return $next($request)->header('Cache-Control', 'no-store, private');
    }
}
