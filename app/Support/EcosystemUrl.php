<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URL a la que lleva una notificación del ecosistema. La mandan el Dashboard o Tickets por el puente, pero el destino
 * se vuelve a validar aquí y al abrirla: solo se aceptan rutas del portal o URL de los sistemas del ecosistema
 * (`config('ecosistema.urls')`, mismo origen exacto), y la URL se RECONSTRUYE con caracteres permitidos (nunca se
 * devuelve el texto recibido): sin esquemas raros, usuario, fragmento ni `//`.
 */
final class EcosystemUrl
{
    private const PATH = '#^/[A-Za-z0-9/_.-]{0,200}$#D';

    private const QUERY = '#^[A-Za-z0-9=&_.%-]{0,200}$#D';

    /**
     * Rutas a las que puede llevar un aviso: pantallas de consulta del portal, Tickets y el Dashboard. Nada de sesión
     * (`/ecosistema/...`, `/logout`), puente (`/interno`), archivos (`/storage`) ni descargas.
     */
    private const ALLOWED = '#^/(?:|(?:inicio|notificaciones|calendario|profile|cuenta|tickets|activities|notifications|chat|tracking|dashboard|insumos|asistencia|horarios|vacaciones|cumpleanos|secciones|agenda)(?:/.*)?)$#D';

    /** Segmentos que descargan archivos o cambian la sesión con un simple GET. */
    private const DENIED = '#/(?:export|exportar|descargar|download|reporte|reportes|salir|logout|preview)(?:/|$)#i';

    /**
     * La URL normalizada (ruta relativa del portal o URL absoluta de un sistema del ecosistema) o null.
     */
    public static function normalize(string $url): ?string
    {
        if ($url === '' || strlen($url) > 300 || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (preg_match(self::PATH, $path) !== 1 || str_contains($path, '//') || str_contains($path, '..')
            || preg_match(self::ALLOWED, $path) !== 1 || preg_match(self::DENIED, $path) === 1) {
            return null;
        }

        $query = $parts['query'] ?? null;

        if ($query !== null && preg_match(self::QUERY, $query) !== 1) {
            return null;
        }

        $suffix = $path.($query !== null ? '?'.$query : '');

        // Ruta del portal.
        if (! isset($parts['scheme']) && ! isset($parts['host'])) {
            return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $suffix : null;
        }

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');

        foreach ((array) config('ecosistema.urls') as $base) {
            $base = rtrim((string) $base, '/');

            if ($base !== '' && strtolower($base) === $origin) {
                return $base.$suffix;
            }
        }

        return null;
    }

    /** URL absoluta lista para redirigir a partir de una normalizada. */
    public static function absolute(string $normalized): string
    {
        return str_starts_with($normalized, '/') ? url($normalized) : $normalized;
    }
}
