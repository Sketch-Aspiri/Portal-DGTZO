<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProvisionAccess;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/**
 * Aprobar una cuenta crea la cuenta en cada subsistema por el puente firmado: ubicación (equipos, estación) tomada
 * del catálogo vivo, roles amplios solo para el administrador del portal, reintentos y conflictos.
 */
class ProvisioningTest extends TestCase
{
    use FakesSubsystems, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSubsystems();
    }

    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    /** @param  array<string, mixed>  $accesos */
    private function approve(User $actor, User $target, array $accesos)
    {
        return $this->asVerified($actor)->post("/usuarios/{$target->id}/aprobar", ['accesos' => $accesos]);
    }

    public function test_approving_creates_the_account_in_each_subsystem_with_signed_calls_and_the_same_password(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create(['email' => 'Ana@Ejemplo.test']);

        $this->approve($jefe, $target, [
            'tickets' => ['activo' => 1, 'rol' => 'coordinador', 'team_ids' => [1, 2]],
            'dashboard' => ['activo' => 1, 'rol' => 'estacion', 'estacion_id' => 5],
        ])->assertRedirect(route('users.show', $target));

        $tickets = UsuarioSubsistema::where('user_id', $target->id)->where('subsistema', 'tickets')->firstOrFail();
        $dashboard = UsuarioSubsistema::where('user_id', $target->id)->where('subsistema', 'dashboard')->firstOrFail();
        $this->assertSame('ok', $tickets->aprovisionamiento);
        $this->assertSame('ok', $dashboard->aprovisionamiento);
        $this->assertNotNull($tickets->aprovisionado_at);

        Http::assertSent(function (Request $request) use ($target): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), 'tickets.test')) {
                return false;
            }

            $this->assertSignedBy($request, self::TICKETS_SECRET);
            $body = $request->data();
            $this->assertSame($target->portal_user_id, $body['portal_user_id']);
            $this->assertSame('ana@ejemplo.test', $body['email']);
            $this->assertSame('coordinador', $body['role']);
            $this->assertSame([1, 2], $body['team_ids']);
            // Se envía el hash bcrypt del portal (la persona entra igual en todos los sistemas), no la contraseña.
            $this->assertSame($target->fresh()->getRawOriginal('password'), $body['password_hash']);
            $this->assertStringNotContainsString('Clave-Segura-2026!', $request->body());

            return true;
        });
        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), 'dashboard.test')) {
                return false;
            }

            $this->assertSignedBy($request, self::DASHBOARD_SECRET);
            $this->assertSame(5, $request->data()['estacion_id']);
            $this->assertSame('estacion', $request->data()['role']);

            return true;
        });
    }

    public function test_the_location_must_come_from_the_live_catalog(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();

        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [99]]])
            ->assertSessionHasErrors('accesos.tickets.team_ids');
        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado']])
            ->assertSessionHasErrors('accesos.tickets.team_ids');
        $this->approve($jefe, $target, ['dashboard' => ['activo' => 1, 'rol' => 'estacion', 'estacion_id' => 77]])
            ->assertSessionHasErrors('accesos.dashboard.estacion_id');
        // El rol de ese sistema que el catálogo no conoce.
        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'dios', 'team_ids' => [1]]])
            ->assertSessionHasErrors('accesos.tickets.rol');

        $this->assertDatabaseCount('usuario_subsistema', 0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_chief_needs_coordinations_and_other_roles_ignore_them(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->create();

        $this->approve($admin, $target, ['tickets' => ['activo' => 1, 'rol' => 'jefe_zona']])
            ->assertSessionHasErrors('accesos.tickets.coordination_ids');
        $this->approve($admin, $target, ['tickets' => ['activo' => 1, 'rol' => 'jefe_zona', 'coordination_ids' => [10], 'team_ids' => [1]]])
            ->assertRedirect();

        // Un jefe de zona no lleva equipos aunque lleguen: solo coordinaciones.
        $context = UsuarioSubsistema::where('user_id', $target->id)->firstOrFail()->contexto;
        $this->assertSame([], $context['team_ids']);
        $this->assertSame([10], $context['coordination_ids']);
    }

    public function test_only_a_portal_administrator_can_grant_broad_roles(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->create();

        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'administrador']])
            ->assertSessionHasErrors('accesos.tickets.rol');
        $this->approve($jefe, $target, ['dashboard' => ['activo' => 1, 'rol' => 'edificio']])
            ->assertSessionHasErrors('accesos.dashboard.rol');
        $this->assertDatabaseCount('usuario_subsistema', 0);

        $this->approve($admin, $target, ['tickets' => ['activo' => 1, 'rol' => 'administrador']])->assertRedirect();
        $this->assertDatabaseHas('usuario_subsistema', ['user_id' => $target->id, 'rol' => 'administrador']);
    }

    public function test_the_form_does_not_offer_broad_roles_to_a_chief(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->create();

        $this->asVerified($jefe)->get("/usuarios/{$target->id}")->assertOk()
            ->assertSee('value="empleado"', false)->assertDontSee('value="administrador"', false)
            ->assertSee('Soporte TI')->assertSee('Tulum');
        $this->asVerified($admin)->get("/usuarios/{$target->id}")->assertOk()->assertSee('value="administrador"', false);
    }

    public function test_an_unavailable_subsystem_blocks_its_access_and_does_not_hide_the_failure(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();
        Cache::flush();
        Http::swap(new Factory);
        Http::fake(['https://tickets.test/interno/portal/catalogo' => Http::response('', 500), 'https://dashboard.test/*' => Http::response($this->dashboardCatalog())]);

        $this->asVerified($jefe)->get("/usuarios/{$target->id}")->assertOk()->assertSee(__('portal.catalog_unavailable'));
        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])
            ->assertSessionHasErrors('accesos.tickets.activo');
        $this->assertSame('pending', $target->fresh()->status->value);
    }

    public function test_an_existing_account_with_that_email_is_a_conflict_not_retried_and_not_paired(): void
    {
        $this->fakeAccountResponse('tickets', Http::response(['estado' => 'correo_existente'], 409));
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();

        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])->assertRedirect();

        $access = UsuarioSubsistema::where('user_id', $target->id)->firstOrFail();
        $this->assertSame('conflicto', $access->aprovisionamiento);
        $this->assertSame(__('portal.provision.conflict'), $access->ultimo_error);
        Http::assertSentCount(2); // un catálogo + un alta, sin reintento.
    }

    public function test_a_server_error_leaves_the_access_in_error_with_a_manual_retry(): void
    {
        $this->fakeAccountResponse('tickets', Http::response('boom', 500));
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();

        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])->assertRedirect();

        $access = UsuarioSubsistema::where('user_id', $target->id)->firstOrFail();
        $this->assertSame('error', $access->aprovisionamiento);
        $this->assertSame(1, $access->intentos);
        $this->asVerified($jefe)->get("/usuarios/{$target->id}")->assertSee(__('portal.approvals.retry'));

        // Reintento manual cuando el subsistema ya responde.
        Queue::fake();
        $this->asVerified($jefe)->post("/usuarios/{$target->id}/aprovisionar/tickets")->assertRedirect(route('users.show', $target));
        Queue::assertPushed(ProvisionAccess::class, fn (ProvisionAccess $job): bool => $job->accessId === $access->id);
        $this->assertSame('pendiente', $access->fresh()->aprovisionamiento);
    }

    public function test_a_network_failure_is_an_error_without_leaking_details(): void
    {
        $this->fakeAccountResponse('tickets', fn () => throw new ConnectionException('cURL error: https://tickets.test/interno/portal/cuentas secret'));
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();

        $this->approve($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])->assertRedirect();

        $access = UsuarioSubsistema::where('user_id', $target->id)->firstOrFail();
        $this->assertSame('error', $access->aprovisionamiento);
        $this->assertSame(__('portal.provision.unreachable'), $access->ultimo_error);
    }

    public function test_retry_is_only_for_accesses_in_error_and_only_for_approvers(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $regular = User::factory()->active()->create();
        $target = User::factory()->active()->create();
        $ok = $target->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);
        $ok->forceFill(['aprovisionamiento' => 'ok'])->save();

        $this->asVerified($jefe)->post("/usuarios/{$target->id}/aprovisionar/tickets")->assertSessionHasErrors('business');
        $this->asVerified($regular)->post("/usuarios/{$target->id}/aprovisionar/tickets")->assertForbidden();
    }

    public function test_without_a_secret_the_subsystem_cannot_be_reached_and_no_call_is_made(): void
    {
        config(['ecosistema.puente.secretos.tickets' => null]);
        Cache::flush();
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();

        $this->asVerified($jefe)->get("/usuarios/{$target->id}")->assertSee(__('portal.catalog_unavailable'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'tickets.test'));
    }
}
