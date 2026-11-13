<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Llamadas firmadas del portal a un subsistema (puente servidor a servidor). Mismo formato que BridgeSignature de
 * Tickets y FirmaPuente del Dashboard (no cambiar uno sin los otros):
 *
 *   firma = hex(hmac_sha256(secreto, METODO \n /ruta \n consulta_normalizada \n sistema \n fecha \n nonce \n sha256(cuerpo)))
 *
 * Los secretos nunca aparecen en errores ni en registros. Una falla de red o de firma devuelve null: quien llama
 * decide si reintenta.
 */
final class BridgeClient
{
    public function configured(string $subsystem): bool
    {
        return strlen((string) config('ecosistema.puente.secretos.'.$subsystem)) >= 32
            && is_string(config('ecosistema.urls.'.$subsystem));
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $body
     */
    public function send(string $subsystem, string $method, string $path, array $query = [], ?array $body = null): ?Response
    {
        if (! $this->configured($subsystem)) {
            return null;
        }

        $method = strtoupper($method);
        $json = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->headers((string) config('ecosistema.puente.secretos.'.$subsystem), $method, $path, $query, $json);
        $timeout = (int) config('ecosistema.puente.timeout', 8);

        try {
            $http = Http::withHeaders($headers)->acceptJson()->timeout($timeout)->connectTimeout(3)->withoutRedirecting();
            $url = rtrim((string) config('ecosistema.urls.'.$subsystem), '/').$path;

            return $method === 'GET'
                ? $http->get($url, $query)
                : $http->withBody($json, 'application/json')->send($method, $url, $query === [] ? [] : ['query' => $query]);
        } catch (Throwable) {
            // Sin detalle: el mensaje de una excepcion de red puede incluir la URL completa.
            return null;
        }
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, string>
     */
    private function headers(string $secret, string $method, string $path, array $query, string $body): array
    {
        $date = (string) now()->getTimestamp();
        $nonce = Str::random(40);
        ksort($query);

        $signature = hash_hmac('sha256', implode("\n", [
            $method,
            '/'.ltrim($path, '/'),
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            'portal',
            $date,
            $nonce,
            hash('sha256', $body),
        ]), $secret);

        return [
            'X-Puente-Sistema' => 'portal',
            'X-Puente-Fecha' => $date,
            'X-Puente-Nonce' => $nonce,
            'X-Puente-Firma' => $signature,
        ];
    }
}
