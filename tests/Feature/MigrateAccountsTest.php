<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountMigrated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** portal:migrar-cuentas trae las cuentas de Dashboard y Tickets al portal sin tocar contraseñas ni duplicar. */
class MigrateAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'ecosistema.urls.tickets' => 'https://tickets.test',
            'ecosistema.urls.dashboard' => 'https://dashboard.test',
            'ecosistema.puente.secretos.tickets' => 'secreto-portal-a-tickets-de-prueba-0123456789abcdef',
            'ecosistema.puente.secretos.dashboard' => 'secreto-portal-a-dashboard-de-prueba-0123456789abc',
        ]);

        Http::fake([
            'https://dashboard.test/interno/portal/cuentas*' => Http::response(['cuentas' => [
                ['email' => 'ana@ejemplo.test', 'nombre' => 'Ana', 'rol' => 'Jefe de Zona', 'vinculada' => false],
                ['email' => 'luis@ejemplo.test', 'nombre' => 'Luis', 'rol' => 'Estación', 'vinculada' => false],
                ['email' => 'raro@ejemplo.test', 'nombre' => 'Raro', 'rol' => 'Inventado', 'vinculada' => false],
            ], 'siguiente' => null]),
            'https://tickets.test/interno/portal/cuentas*' => Http::response(['cuentas' => [
                ['email' => 'ANA@ejemplo.test', 'nombre' => 'Ana', 'rol' => 'Coordinador', 'vinculada' => false],
                ['email' => 'ya@ejemplo.test', 'nombre' => 'Ya', 'rol' => 'Empleado', 'vinculada' => true],
            ], 'siguiente' => null]),
        ]);
    }

    public function test_simulation_changes_nothing(): void
    {
        User::factory()->create(['email' => 'ya@ejemplo.test']);

        $this->artisan('portal:migrar-cuentas')->assertSuccessful();

        $this->assertSame(1, User::count());
    }

    public function test_execute_creates_active_accounts_with_one_account_per_email_and_existing_access(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ya@ejemplo.test']);

        $this->artisan('portal:migrar-cuentas', ['--ejecutar' => true])->assertSuccessful();

        $ana = User::where('email', 'ana@ejemplo.test')->firstOrFail();
        $this->assertTrue($ana->isActive());
        $this->assertSame(['dashboard' => 'jefe_zona', 'tickets' => 'coordinador'], $ana->accesos->pluck('rol', 'subsistema')->all());
        $this->assertSame(['conflicto'], $ana->accesos->pluck('aprovisionamiento')->unique()->values()->all());
        $this->assertTrue(User::where('email', 'luis@ejemplo.test')->exists());
        $this->assertFalse(User::where('email', 'raro@ejemplo.test')->exists(), 'rol no reconocido: se omite');
        $this->assertSame(1, User::where('email', 'ya@ejemplo.test')->count(), 'no duplica a quien ya está');
        Notification::assertNothingSent();

        // Repetirlo no crea nada nuevo.
        $this->artisan('portal:migrar-cuentas', ['--ejecutar' => true])->assertSuccessful();
        $this->assertSame(3, User::count());
    }

    public function test_avisar_sends_the_email_and_solo_limits_to_one_account(): void
    {
        Notification::fake();

        $this->artisan('portal:migrar-cuentas', ['--ejecutar' => true, '--avisar' => true, '--solo' => 'luis@ejemplo.test'])->assertSuccessful();

        $this->assertSame(1, User::count());
        Notification::assertSentTo(User::firstOrFail(), AccountMigrated::class);
    }

    public function test_it_fails_without_changes_when_a_subsystem_does_not_respond(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['https://tickets.test/*' => Http::response('', 500), 'https://dashboard.test/*' => Http::response(['cuentas' => [['email' => 'x@ejemplo.test', 'nombre' => 'X', 'rol' => 'Estación']], 'siguiente' => null])]);
        Cache::flush();

        $this->artisan('portal:migrar-cuentas', ['--ejecutar' => true])->assertFailed();

        $this->assertSame(0, User::count());
    }
}
