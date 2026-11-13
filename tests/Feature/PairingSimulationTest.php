<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

class PairingSimulationTest extends TestCase
{
    use FakesSubsystems, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSubsystems();
    }

    public function test_the_simulation_reports_overlaps_by_email_and_changes_nothing(): void
    {
        User::factory()->active()->create(['email' => 'comun@ejemplo.test']);
        User::factory()->create(['email' => 'solo-portal@ejemplo.test']);

        Http::swap(new Factory);
        Http::fake([
            'https://tickets.test/interno/portal/cuentas*' => Http::response([
                'cuentas' => [
                    ['email' => 'comun@ejemplo.test', 'nombre' => 'Común', 'estado' => 'active', 'rol' => 'empleado', 'vinculada' => true, 'emparejada' => false],
                    ['email' => 'solo-tickets@ejemplo.test', 'nombre' => 'Solo', 'estado' => 'active', 'rol' => 'empleado', 'vinculada' => false, 'emparejada' => false],
                ],
                'siguiente' => null,
            ]),
            'https://dashboard.test/interno/portal/cuentas*' => Http::response(['cuentas' => [], 'siguiente' => null]),
        ]);

        $this->artisan('portal:emparejar-simulacion', ['--listar' => true])
            ->expectsOutputToContain('Cuentas en el portal: 2')
            ->expectsOutputToContain('Tickets: 2 cuentas')
            ->expectsOutputToContain('comun@ejemplo.test')
            ->expectsOutputToContain('solo-tickets@ejemplo.test')
            ->expectsOutputToContain('Simulación: no se cambió nada.')
            ->assertSuccessful();

        // Solo lecturas (GET): ningún alta ni cambio.
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
        $this->assertDatabaseCount('usuario_subsistema', 0);
        $this->assertSame(2, User::count());
    }

    public function test_a_subsystem_that_does_not_answer_is_reported_and_the_run_fails(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'https://tickets.test/*' => Http::response('', 500),
            'https://dashboard.test/*' => Http::response(['cuentas' => [], 'siguiente' => null]),
        ]);

        $this->artisan('portal:emparejar-simulacion')
            ->expectsOutputToContain('tickets: no responde')
            ->assertFailed();
    }
}
