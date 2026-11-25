<?php

declare(strict_types=1);

namespace App\Services\Tutoriales;

use Illuminate\Support\Carbon;

/** Una entrada de config/guias.php. */
final class Guia
{
    /** @param list<string>|null $roles */
    public function __construct(
        public readonly string $slug,
        public readonly string $sistema,
        public readonly string $titulo,
        public readonly string $resumen,
        public readonly ?array $roles,
        public readonly bool $publica,
        public readonly Carbon $publicada,
        public readonly Carbon $actualizada,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desde(string $slug, array $datos): self
    {
        return new self(
            slug: $slug,
            sistema: $datos['sistema'],
            titulo: $datos['titulo'],
            resumen: $datos['resumen'],
            roles: $datos['roles'] ?? null,
            publica: (bool) ($datos['publica'] ?? false),
            publicada: Carbon::parse($datos['publicada']),
            actualizada: Carbon::parse($datos['actualizada'] ?? $datos['publicada']),
        );
    }

    public function archivo(): string
    {
        return config('guias.ruta')."/{$this->sistema}/{$this->slug}.md";
    }

    public function carpetaImagenes(): string
    {
        return config('guias.ruta')."/img/{$this->sistema}/{$this->slug}";
    }

    /** «nuevo», «actualizado» o null. */
    public function novedad(int $dias): ?string
    {
        $limite = now()->subDays($dias)->startOfDay();

        if ($this->publicada->gte($limite)) {
            return $this->actualizada->gt($this->publicada) ? 'actualizado' : 'nuevo';
        }

        return $this->actualizada->gte($limite) ? 'actualizado' : null;
    }
}
