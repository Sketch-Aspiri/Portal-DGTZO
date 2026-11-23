<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use App\Services\TwoFactorService;
use App\Services\UserApprovalService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/**
 * Administración completa de cuentas desde el portal: crear, cambiar rol/ubicación, quitar un acceso, dar de baja
 * y reactivar, con el cambio llevado a Dashboard y Tickets por el puente firmado.
 */
class ManageAccountsTest extends TestCase
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

    /** Cuenta activa con un acceso a Tickets ya creado en el subsistema. */
    private function personWithTickets(string $rol = 'empleado'): User
    {
        $user = User::factory()->active()->create();
        $access = $user->todosLosAccesos()->create(['subsistema' => 'tickets', 'rol' => $rol, 'contexto' => ['team_ids' => [1], 'coordination_ids' => []]]);
        $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::OK, 'aprovisionado_at' => now()])->save();

        return $user;
    }

    private function sentPut(string $subsystem, ?array $expect = null): void
    {
        Http::assertSent(function (Request $request) use ($subsystem, $expect): bool {
            if ($request->method() !== 'PUT' || ! str_contains($request->url(), "$subsystem.test/interno/portal/cuentas/")) {
                return false;
            }

            return $expect === null || array_intersect_key($request->data(), $expect) == $expect;
        });
    }

    // --- Crear cuenta -------------------------------------------------------------------------------------------

    public function test_an_admin_creates_an_account_and_the_person_gets_a_password_link_without_creating_accounts_yet(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->post(route('users.store'), [
            'name' => 'Ana López', 'email' => 'ana@ejemplo.test', 'portal_role' => 'usuario',
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]],
        ])->assertRedirect();

        $user = User::where('email', 'ana@ejemplo.test')->firstOrFail();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(PortalRole::Usuario, $user->portal_role);
        $this->assertSame('pendiente', $user->accesos()->firstOrFail()->aprovisionamiento);
        Notification::assertSentTo($user, ResetPassword::class);
        // Las cuentas de los sistemas esperan a que la persona defina su contraseña (para entrar allá con la misma).
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/interno/portal/cuentas'));
        $this->assertDatabaseHas('activity_log', ['event' => 'created_by_admin', 'causer_id' => $admin->id, 'subject_id' => $user->id]);
    }

    public function test_when_the_person_sets_a_password_their_accounts_are_created(): void
    {
        $admin = User::factory()->administrador()->create();
        $user = app(UserApprovalService::class)->createAccount($admin, 'Ana López', 'ana@ejemplo.test', PortalRole::Usuario, [
            'tickets' => ['rol' => 'empleado', 'contexto' => ['team_ids' => [1], 'coordination_ids' => []]],
        ]);
        Http::assertNothingSent();

        $this->post('/reset-password', [
            'token' => Password::createToken($user), 'email' => 'ana@ejemplo.test',
            'password' => 'Clave-Segura-2026!', 'password_confirmation' => 'Clave-Segura-2026!',
        ])->assertRedirect(route('login'));

        $this->assertSame('ok', $user->accesos()->firstOrFail()->aprovisionamiento);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'tickets.test/interno/portal/cuentas'));
    }

    public function test_only_an_administrator_can_create_an_account_with_a_wider_portal_role(): void
    {
        $jefe = User::factory()->jefeZona()->create();

        $this->asVerified($jefe)->post(route('users.store'), [
            'name' => 'Otro Admin', 'email' => 'otro@ejemplo.test', 'portal_role' => 'administrador',
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]],
        ])->assertSessionHasErrors('business');

        $this->assertDatabaseMissing('users', ['email' => 'otro@ejemplo.test']);
    }

    public function test_creating_an_account_needs_a_new_email_and_at_least_one_system(): void
    {
        $admin = User::factory()->administrador()->create();
        User::factory()->active()->create(['email' => 'ya@ejemplo.test']);

        $this->asVerified($admin)->post(route('users.store'), ['name' => 'Ya Existe', 'email' => 'ya@ejemplo.test', 'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]]])
            ->assertSessionHasErrors('email');
        $this->asVerified($admin)->post(route('users.store'), ['name' => 'Sin Sistema', 'email' => 'nuevo@ejemplo.test'])
            ->assertSessionHasErrors('accesos');
    }

    public function test_a_normal_user_cannot_reach_the_create_form(): void
    {
        $user = User::factory()->active()->create();

        $this->asVerified($user)->get(route('users.create'))->assertForbidden();
    }

    // --- Cambiar rol / ubicación --------------------------------------------------------------------------------

    public function test_an_approver_changes_the_role_and_teams_and_the_subsystem_gets_the_signed_update(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets('empleado');

        $this->asVerified($admin)->put(route('users.access.update', [$target, 'tickets']), [
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'coordinador', 'team_ids' => [2]]],
        ])->assertRedirect(route('users.show', $target));

        $access = $target->accesos()->firstOrFail();
        $this->assertSame('coordinador', $access->rol);
        $this->assertSame([2], $access->contexto['team_ids']);
        $this->assertSame('ok', $access->aprovisionamiento);
        $this->assertNull($access->accion_pendiente);
        $this->sentPut('tickets', ['role' => 'coordinador', 'estado' => 'activo']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $this->assertSignedBy($request, self::TICKETS_SECRET) === null);
        $this->assertDatabaseHas('activity_log', ['event' => 'access_changed', 'causer_id' => $admin->id, 'subject_id' => $target->id]);
    }

    public function test_a_chief_cannot_raise_a_role_to_a_wide_one(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = $this->personWithTickets('empleado');

        $this->asVerified($jefe)->put(route('users.access.update', [$target, 'tickets']), [
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'administrador']],
        ])->assertSessionHasErrors('accesos.tickets.rol');

        $this->assertSame('empleado', $target->accesos()->firstOrFail()->rol);
    }

    public function test_a_chief_cannot_touch_an_account_that_already_has_a_wide_role(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = $this->personWithTickets('administrador');

        $this->asVerified($jefe)->put(route('users.access.update', [$target, 'tickets']), [
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]],
        ])->assertSessionHasErrors('business');
        $this->asVerified($jefe)->delete(route('users.access.revoke', [$target, 'tickets']))->assertSessionHasErrors('business');
        $this->asVerified($jefe)->post(route('users.deactivate', $target))->assertSessionHasErrors('business');

        $this->assertSame('administrador', $target->accesos()->firstOrFail()->rol);
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_changing_the_role_of_an_account_not_paired_in_the_subsystem_reports_it_without_retrying(): void
    {
        $this->fakeSyncResponse('tickets', Http::response(['error' => 'no_encontrada'], 404));
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets('empleado');

        $this->asVerified($admin)->put(route('users.access.update', [$target, 'tickets']), [
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'coordinador', 'team_ids' => [2]]],
        ])->assertRedirect();

        $access = $target->accesos()->firstOrFail();
        $this->assertSame('error', $access->aprovisionamiento);
        $this->assertSame('actualizar', $access->accion_pendiente);
        $this->assertNotEmpty($access->ultimo_error);
    }

    // --- Quitar un acceso ---------------------------------------------------------------------------------------

    public function test_revoking_an_access_deactivates_the_subsystem_account_and_hides_it_here(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();

        $this->asVerified($admin)->delete(route('users.access.revoke', [$target, 'tickets']))->assertRedirect(route('users.show', $target));

        $this->sentPut('tickets', ['estado' => 'inactivo']);
        $this->assertSame(0, $target->accesos()->count());
        $row = $target->todosLosAccesos()->firstOrFail();
        $this->assertNotNull($row->revocado_at);
        $this->assertNull($row->accion_pendiente);
        $this->assertSame([], $target->fresh()->subsistemasAccesibles());
        $this->assertDatabaseHas('activity_log', ['event' => 'access_revoked', 'causer_id' => $admin->id, 'subject_id' => $target->id]);
    }

    public function test_an_access_removed_before_can_be_granted_again_and_reactivates_the_existing_account(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();
        $this->asVerified($admin)->delete(route('users.access.revoke', [$target, 'tickets']));

        $this->asVerified($admin)->post(route('users.grant', $target), [
            'accesos' => ['tickets' => ['activo' => 1, 'rol' => 'coordinador', 'team_ids' => [2]]],
        ])->assertRedirect(route('users.show', $target));

        $this->assertSame(1, $target->todosLosAccesos()->count());
        $access = $target->accesos()->firstOrFail();
        $this->assertSame('coordinador', $access->rol);
        $this->assertNull($access->revocado_at);
        $this->sentPut('tickets', ['estado' => 'activo', 'role' => 'coordinador']);
    }

    public function test_revoking_when_the_subsystem_has_no_paired_account_just_hides_it(): void
    {
        $this->fakeSyncResponse('tickets', Http::response(['error' => 'no_encontrada'], 404));
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();

        $this->asVerified($admin)->delete(route('users.access.revoke', [$target, 'tickets']))->assertRedirect();

        $row = $target->todosLosAccesos()->firstOrFail();
        $this->assertNotNull($row->revocado_at);
        $this->assertSame('ok', $row->aprovisionamiento);
        $this->assertNull($row->accion_pendiente);
    }

    public function test_a_failing_subsystem_keeps_the_pending_action_and_a_manual_retry_finishes_it(): void
    {
        $this->fakeSyncResponse('tickets', Http::response([], 422));
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();
        $this->asVerified($admin)->delete(route('users.access.revoke', [$target, 'tickets']));

        $row = $target->todosLosAccesos()->firstOrFail();
        $this->assertSame('error', $row->aprovisionamiento);
        $this->assertSame('revocar', $row->accion_pendiente);

        $this->fakeSyncResponse('tickets', Http::response(['estado' => 'actualizada']));
        $this->asVerified($admin)->post(route('users.retry', [$target, 'tickets']))->assertRedirect();

        $row->refresh();
        $this->assertSame('ok', $row->aprovisionamiento);
        $this->assertNull($row->accion_pendiente);
    }

    // --- Baja y reactivación ------------------------------------------------------------------------------------

    public function test_deactivating_an_account_blocks_the_portal_and_deactivates_every_system(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();
        $access = $target->todosLosAccesos()->create(['subsistema' => 'dashboard', 'rol' => 'estacion', 'contexto' => ['estacion_id' => 5, 'area' => 'rh']]);
        $access->forceFill(['aprovisionamiento' => UsuarioSubsistema::OK, 'aprovisionado_at' => now()])->save();

        $this->asVerified($admin)->post(route('users.deactivate', $target))->assertRedirect(route('users.show', $target));

        $this->assertSame(UserStatus::Inactive, $target->fresh()->status);
        $this->sentPut('tickets', ['estado' => 'inactivo']);
        $this->sentPut('dashboard', ['estado' => 'inactivo']);
        $this->assertSame(2, $target->accesos()->where('aprovisionamiento', 'ok')->count());
        $this->assertDatabaseHas('activity_log', ['event' => 'deactivated', 'causer_id' => $admin->id, 'subject_id' => $target->id]);

        // Sin sesión útil en el portal.
        $this->asVerified($target->fresh())->get(route('dashboard'))->assertRedirect(route('account.status'));
    }

    public function test_nobody_deactivates_their_own_account_nor_the_last_administrator(): void
    {
        $admin = User::factory()->administrador()->create();
        $this->asVerified($admin)->post(route('users.deactivate', $admin))->assertSessionHasErrors('business');
        $this->assertSame(UserStatus::Active, $admin->fresh()->status);

        // Con un solo administrador activo, otro administrador (inactivo ya no cuenta) no puede dejar al portal sin ninguno.
        $other = User::factory()->administrador()->create();
        $this->asVerified($admin)->post(route('users.deactivate', $other))->assertRedirect();
        $this->asVerified($other->fresh())->post(route('users.deactivate', $admin))->assertRedirect(route('account.status'));
        $this->assertSame(UserStatus::Active, $admin->fresh()->status);
    }

    public function test_reactivating_restores_access_and_reaches_the_subsystems(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();
        $this->asVerified($admin)->post(route('users.deactivate', $target));

        $this->asVerified($admin)->post(route('users.reactivate', $target))->assertRedirect(route('users.show', $target));

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->sentPut('tickets', ['estado' => 'activo']);
        $this->assertSame('ok', $target->accesos()->firstOrFail()->aprovisionamiento);
        $this->assertDatabaseHas('activity_log', ['event' => 'reactivated', 'causer_id' => $admin->id, 'subject_id' => $target->id]);
    }

    public function test_a_rejected_account_that_never_had_access_is_not_reactivated(): void
    {
        $admin = User::factory()->administrador()->create();
        $rejected = User::factory()->inactive()->create();

        $this->asVerified($admin)->post(route('users.reactivate', $rejected))->assertSessionHasErrors('business');
        $this->assertSame(UserStatus::Inactive, $rejected->fresh()->status);
    }

    // --- Permisos y pantalla ------------------------------------------------------------------------------------

    public function test_a_normal_user_cannot_manage_accounts(): void
    {
        $user = User::factory()->active()->create();
        $target = $this->personWithTickets();

        $this->asVerified($user)->post(route('users.deactivate', $target))->assertForbidden();
        $this->asVerified($user)->delete(route('users.access.revoke', [$target, 'tickets']))->assertForbidden();
        $this->asVerified($user)->put(route('users.access.update', [$target, 'tickets']), ['accesos' => []])->assertForbidden();
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_the_account_page_offers_the_new_actions_and_no_longer_says_that_only_adding_is_possible(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();

        $this->asVerified($admin)->get(route('users.show', $target))->assertOk()
            ->assertSee(route('users.deactivate', $target), false)
            ->assertSee(route('users.access.revoke', [$target, 'tickets']), false)
            ->assertSee(route('users.access.update', [$target, 'tickets']), false)
            ->assertSee(route('users.invite', $target), false)
            ->assertDontSee(__('portal.approvals.no_removal'));

        $this->asVerified($admin)->get(route('users.index'))->assertOk()->assertSee(route('users.create'), false);
        $this->asVerified($admin)->get(route('users.create'))->assertOk()->assertSee('name="portal_role"', false);
    }

    public function test_the_page_of_your_own_account_offers_no_management_actions(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->get(route('users.show', $admin))->assertOk()
            ->assertDontSee(route('users.deactivate', $admin), false);
    }

    public function test_sending_the_password_link_notifies_the_person(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();
        $target = $this->personWithTickets();

        $this->asVerified($admin)->post(route('users.invite', $target))->assertRedirect();

        Notification::assertSentTo($target, ResetPassword::class);
    }
}
