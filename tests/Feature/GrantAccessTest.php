<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProvisionAccess;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/**
 * Dar acceso a otros sistemas a una cuenta ya activa: solo agrega, nunca a uno mismo, con la misma validación
 * (catálogo vivo, roles amplios) y el mismo aprovisionamiento que la aprobación.
 */
class GrantAccessTest extends TestCase
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
    private function grant(User $actor, User $target, array $accesos)
    {
        return $this->asVerified($actor)->post("/usuarios/{$target->id}/accesos", ['accesos' => $accesos]);
    }

    public function test_an_approver_gives_an_active_account_access_and_the_account_is_created(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->grant($admin, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])
            ->assertRedirect(route('users.show', $target));

        $access = UsuarioSubsistema::where('user_id', $target->id)->firstOrFail();
        $this->assertSame('ok', $access->aprovisionamiento);
        $this->assertSame('empleado', $access->rol);
        $this->assertSame([1], $access->contexto['team_ids']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'tickets.test/interno/portal/cuentas'));
        $this->assertDatabaseHas('activity_log', ['event' => 'access_granted', 'causer_id' => $admin->id, 'subject_id' => $target->id]);
    }

    public function test_an_existing_account_in_the_subsystem_still_gets_its_card_as_a_conflict(): void
    {
        $this->fakeAccountResponse('dashboard', Http::response(['estado' => 'correo_existente'], 409));
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->active()->create();

        $this->grant($admin, $target, ['dashboard' => ['activo' => 1, 'rol' => 'administrador']])->assertRedirect();

        $this->assertSame('conflicto', UsuarioSubsistema::where('user_id', $target->id)->value('aprovisionamiento'));
        // El lanzador lo muestra igual: la persona entra con su cuenta de allá, que se vincula con su contraseña.
        $target->load('accesos');
        $this->assertArrayHasKey('dashboard', $target->subsistemasAccesibles());
    }

    public function test_nobody_can_give_themselves_access(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->grant($admin, $admin, ['tickets' => ['activo' => 1, 'rol' => 'administrador']])->assertSessionHasErrors('business');
        $this->assertDatabaseCount('usuario_subsistema', 0);

        // Y la pantalla no ofrece el formulario en la propia cuenta.
        $this->asVerified($admin)->get("/usuarios/{$admin->id}")->assertOk()->assertDontSee(__('portal.approvals.add_access_button'));
    }

    public function test_it_only_adds_systems_the_person_does_not_have_yet(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->active()->create();
        $target->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);

        $this->grant($admin, $target, ['tickets' => ['activo' => 1, 'rol' => 'administrador']])
            ->assertSessionHasErrors('accesos.tickets.activo');
        $this->assertSame('empleado', $target->accesos()->where('subsistema', 'tickets')->value('rol'));

        // La pantalla ofrece solo el que falta.
        $this->asVerified($admin)->get("/usuarios/{$target->id}")->assertOk()
            ->assertSee(__('portal.approvals.add_access_button'))
            ->assertSee('accesos[dashboard][activo]', false)
            ->assertDontSee('accesos[tickets][activo]', false);
    }

    public function test_the_form_is_not_offered_when_the_person_has_every_system(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->active()->create();
        $target->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);
        $target->accesos()->create(['subsistema' => 'dashboard', 'rol' => 'estacion', 'contexto' => ['estacion_id' => 5]]);

        $this->asVerified($admin)->get("/usuarios/{$target->id}")->assertOk()
            ->assertSee(__('portal.approvals.has_all'))->assertDontSee(__('portal.approvals.add_access_button'));
    }

    public function test_only_active_accounts_receive_access_this_way(): void
    {
        $admin = User::factory()->administrador()->create();
        $pending = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        $this->grant($admin, $pending, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])->assertSessionHasErrors('business');
        $this->grant($admin, $inactive, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])->assertSessionHasErrors('business');
        $this->assertDatabaseCount('usuario_subsistema', 0);
    }

    public function test_the_same_validation_as_approval_applies(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->active()->create();

        // Rol amplio: solo el administrador del portal.
        $this->grant($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'administrador']])->assertSessionHasErrors('accesos.tickets.rol');
        // Ubicación fuera del catálogo vivo.
        $this->grant($jefe, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [99]]])->assertSessionHasErrors('accesos.tickets.team_ids');
        $this->grant($jefe, $target, ['dashboard' => ['activo' => 1, 'rol' => 'estacion', 'estacion_id' => 77]])->assertSessionHasErrors('accesos.dashboard.estacion_id');
        $this->assertDatabaseCount('usuario_subsistema', 0);
    }

    public function test_regular_users_cannot_give_access(): void
    {
        $regular = User::factory()->active()->create();
        $target = User::factory()->active()->create();

        $this->actingAs($regular)->post("/usuarios/{$target->id}/accesos", ['accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]]])
            ->assertForbidden();
        $this->assertDatabaseCount('usuario_subsistema', 0);
    }

    public function test_the_provisioning_job_is_queued_after_the_access_is_saved(): void
    {
        Queue::fake();
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->active()->create();

        $this->grant($admin, $target, ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]])->assertRedirect();

        $access = UsuarioSubsistema::where('user_id', $target->id)->firstOrFail();
        $this->assertSame('pendiente', $access->aprovisionamiento);
        Queue::assertPushed(ProvisionAccess::class, fn (ProvisionAccess $job): bool => $job->accessId === $access->id);
    }
}
