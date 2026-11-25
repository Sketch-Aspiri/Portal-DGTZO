<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\EcosystemUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Todas las pantallas a las que enlazan hoy los avisos del Dashboard y de Tickets. Si el portal no acepta una, el aviso
 * se rechaza y no llega: al agregar un aviso nuevo en esos sistemas, su ruta se agrega aquí.
 */
class EnlacesDeAvisosTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function enlaces(): array
    {
        $rutas = [
            'dashboard' => [
                '/insumos/5', '/insumos/estaciones/3', '/insumos/cotizaciones/8', '/asistencia/captura', '/cumpleanos',
                '/vacaciones', '/vacaciones/solicitudes', '/vacaciones/seleccion', '/vacaciones/mi',
                '/agua/4', '/ptar/4', '/ptar/estaciones/3',
                '/horarios', '/horarios/solicitudes', '/horarios/consolidados',
            ],
            'tickets' => ['/tickets/12', '/activities/9', '/chat', '/incoming-emails', '/users/7'],
        ];

        $casos = [];
        foreach ($rutas as $sistema => $lista) {
            foreach ($lista as $ruta) {
                $casos[$sistema.' '.$ruta] = [$sistema, $ruta];
            }
        }

        return $casos;
    }

    #[DataProvider('enlaces')]
    public function test_the_portal_accepts_every_link_the_systems_send(string $sistema, string $ruta): void
    {
        $url = config('ecosistema.urls.'.$sistema).$ruta;

        $this->assertSame($url, EcosystemUrl::normalize($url));
    }

    public function test_it_still_rejects_downloads_session_routes_and_foreign_hosts(): void
    {
        $dashboard = config('ecosistema.urls.dashboard');

        foreach (['/agua/descargar', '/ptar/descargar', '/agua/exportar', '/ecosistema/salir', '/logout', '/interno/x', '/storage/a'] as $ruta) {
            $this->assertNull(EcosystemUrl::normalize($dashboard.$ruta), $ruta);
        }

        $this->assertNull(EcosystemUrl::normalize('https://ejemplo.invalid/agua/1'));
    }
}
