<?php

declare(strict_types=1);

namespace App\Support;

/** Lectura única del catálogo de config/subsistemas.php. */
final class Subsistemas
{
    /** @return array<string, array<string, mixed>> */
    public static function todos(): array
    {
        return config('subsistemas', []);
    }

    /** @return array<string, mixed>|null */
    public static function get(string $clave): ?array
    {
        return self::todos()[$clave] ?? null;
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }

    /** @return list<string> */
    public static function rolesDe(string $clave): array
    {
        return array_keys(self::get($clave)['roles'] ?? []);
    }
}
