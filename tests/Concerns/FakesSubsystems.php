<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Dashboard y Tickets simulados: puente configurado, catálogos fijos y alta de cuentas que responde "creada".
 * Las pruebas cambian la respuesta con fakeAccountResponse().
 */
trait FakesSubsystems
{
    protected const TICKETS_SECRET = 'secreto-portal-a-tickets-de-prueba-0123456789abcdef';

    protected const DASHBOARD_SECRET = 'secreto-portal-a-dashboard-de-prueba-0123456789abc';

    protected function fakeSubsystems(): void
    {
        Cache::flush();

        config([
            'ecosistema.urls.tickets' => 'https://tickets.test',
            'ecosistema.urls.dashboard' => 'https://dashboard.test',
            'ecosistema.puente.secretos.tickets' => self::TICKETS_SECRET,
            'ecosistema.puente.secretos.dashboard' => self::DASHBOARD_SECRET,
        ]);

        Http::fake([
            'https://tickets.test/interno/portal/catalogo' => Http::response($this->ticketsCatalog()),
            'https://dashboard.test/interno/portal/catalogo' => Http::response($this->dashboardCatalog()),
            'https://tickets.test/interno/portal/cuentas' => Http::response(['estado' => 'creada', 'user_id' => 1]),
            'https://dashboard.test/interno/portal/cuentas' => Http::response(['estado' => 'creada', 'user_id' => 1]),
            'https://tickets.test/interno/portal/cuentas/*' => Http::response(['estado' => 'actualizada', 'user_id' => 1]),
            'https://dashboard.test/interno/portal/cuentas/*' => Http::response(['estado' => 'actualizada', 'user_id' => 1]),
        ]);
    }

    /** @return array<string, mixed> */
    protected function ticketsCatalog(): array
    {
        return [
            'sistema' => 'tickets',
            'roles' => [
                ['clave' => 'administrador', 'nombre' => 'Administrador', 'requiere_equipo' => false, 'requiere_coordinaciones' => false],
                ['clave' => 'jefe_zona', 'nombre' => 'Jefe de zona', 'requiere_equipo' => false, 'requiere_coordinaciones' => true],
                ['clave' => 'coordinador', 'nombre' => 'Coordinador', 'requiere_equipo' => true, 'requiere_coordinaciones' => false],
                ['clave' => 'empleado', 'nombre' => 'Empleado', 'requiere_equipo' => true, 'requiere_coordinaciones' => false],
            ],
            'equipos' => [
                ['id' => 1, 'nombre' => 'Soporte TI', 'coordinacion_id' => 10, 'coordinacion' => 'Sistemas', 'estacion' => 'Oficina Zonal'],
                ['id' => 2, 'nombre' => 'Redes', 'coordinacion_id' => 10, 'coordinacion' => 'Sistemas', 'estacion' => 'Oficina Zonal'],
                ['id' => 3, 'nombre' => 'RH Tulum', 'equipo_central_id' => 1, 'area' => 'rh', 'codigo_estacion' => '23', 'coordinacion_id' => 10, 'coordinacion' => 'Sistemas', 'estacion' => 'Oficina Zonal'],
            ],
            'coordinaciones' => [['id' => 10, 'nombre' => 'Sistemas', 'estacion' => 'Oficina Zonal']],
        ];
    }

    /** @return array<string, mixed> */
    protected function dashboardCatalog(): array
    {
        return [
            'sistema' => 'dashboard',
            'roles' => [
                ['clave' => 'jefe_zona', 'nombre' => 'Jefe de Zona', 'requiere_estacion' => false],
                ['clave' => 'administrador', 'nombre' => 'Administrador', 'requiere_estacion' => false],
                ['clave' => 'edificio', 'nombre' => 'Edificio', 'requiere_estacion' => false],
                ['clave' => 'estacion', 'nombre' => 'Estación', 'requiere_estacion' => true, 'requiere_area' => true],
            ],
            'areas' => [['clave' => 'rh', 'nombre' => 'Recursos Humanos'], ['clave' => 'rm', 'nombre' => 'Recursos Materiales']],
            'estaciones' => [['id' => 5, 'nombre' => 'Tulum', 'codigo' => '23']],
        ];
    }

    /** Cambia lo que responde un subsistema al crear la cuenta (por omisión "creada"). */
    protected function fakeAccountResponse(string $subsystem, mixed $response): void
    {
        // El primer fake que coincide gana: se parte de un cliente HTTP nuevo para que este sí se aplique.
        Http::swap(new Factory);
        Cache::flush();

        Http::fake([
            "https://$subsystem.test/interno/portal/cuentas" => $response,
            'https://tickets.test/interno/portal/catalogo' => Http::response($this->ticketsCatalog()),
            'https://dashboard.test/interno/portal/catalogo' => Http::response($this->dashboardCatalog()),
        ]);
    }

    /** Cambia lo que responde un subsistema a los cambios posteriores al alta (PUT cuentas/{id}). */
    protected function fakeSyncResponse(string $subsystem, mixed $response): void
    {
        Http::swap(new Factory);
        Cache::flush();

        // El otro subsistema conserva su respuesta por omision (no se repite la llave del que se cambia).
        $other = $subsystem === 'tickets' ? 'dashboard' : 'tickets';

        Http::fake([
            "https://$subsystem.test/interno/portal/cuentas/*" => $response,
            "https://$other.test/interno/portal/cuentas/*" => Http::response(['estado' => 'actualizada']),
            'https://tickets.test/interno/portal/catalogo' => Http::response($this->ticketsCatalog()),
            'https://dashboard.test/interno/portal/catalogo' => Http::response($this->dashboardCatalog()),
            'https://tickets.test/interno/portal/cuentas' => Http::response(['estado' => 'creada', 'user_id' => 1]),
            'https://dashboard.test/interno/portal/cuentas' => Http::response(['estado' => 'creada', 'user_id' => 1]),
        ]);
    }

    /** Verifica que la petición salió firmada con la fórmula del puente y el secreto indicado. */
    protected function assertSignedBy(Request $request, string $secret): void
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        ksort($query);

        $canonical = implode("\n", [
            $request->method(),
            (string) parse_url($request->url(), PHP_URL_PATH),
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            'portal',
            $request->header('X-Puente-Fecha')[0],
            $request->header('X-Puente-Nonce')[0],
            hash('sha256', $request->body()),
        ]);

        $this->assertSame('portal', $request->header('X-Puente-Sistema')[0]);
        $this->assertTrue(hash_equals(hash_hmac('sha256', $canonical, $secret), $request->header('X-Puente-Firma')[0]), 'firma del puente inválida');
    }
}
