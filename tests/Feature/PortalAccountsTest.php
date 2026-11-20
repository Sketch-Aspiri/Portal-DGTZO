<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\NewUserPendingApproval;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

class PortalAccountsTest extends TestCase
{
    use FakesSubsystems, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSubsystems();
    }

    private const PASSWORD = 'Clave-Segura-2026!';

    /** Quienes aprueban deben pasar el 2FA en la sesión para ver cualquier pantalla. */
    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    /** @return array<string, string> */
    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    public function test_welcome_page_shows_the_system_name_and_links(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Sistema de Gestión Territorial Zona Oriente')
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
    }

    public function test_registration_creates_a_pending_account_without_login_and_notifies_approvers(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();
        $jefe = User::factory()->jefeZona()->create();
        $other = User::factory()->active()->create();

        $this->post('/register', $this->registrationData())->assertRedirect(route('login'));

        $this->assertGuest();
        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNotNull($user->portal_user_id);
        $this->assertDatabaseCount('usuario_subsistema', 0);

        Notification::assertSentTo([$admin, $jefe], NewUserPendingApproval::class);
        Notification::assertNotSentTo($other, NewUserPendingApproval::class);
    }

    public function test_registration_rejects_weak_passwords_duplicate_and_invalid_emails(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->post('/register', $this->registrationData(['password' => 'corta1', 'password_confirmation' => 'corta1']))
            ->assertSessionHasErrors('password');
        $this->post('/register', $this->registrationData())->assertSessionHasErrors('email');
        $this->post('/register', $this->registrationData(['email' => 'x@sistema.invalid']))
            ->assertSessionHasErrors('email');
        $this->post('/register', $this->registrationData(['email' => 'otra@example.com', 'name' => '<b>x</b>']))
            ->assertSessionHasErrors('name');
    }

    public function test_status_cannot_be_mass_assigned_through_registration(): void
    {
        $this->post('/register', $this->registrationData(['status' => 'active', 'portal_role' => 'administrador']));

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertFalse($user->canApprove());
    }

    public function test_pending_user_can_log_in_but_only_sees_the_status_page(): void
    {
        $user = User::factory()->create(['email' => 'p@example.com']);

        $this->post('/login', ['email' => 'p@example.com', 'password' => self::PASSWORD])->assertRedirect();
        $this->actingAs($user)->get('/inicio')->assertRedirect(route('account.status'));
        $this->actingAs($user)->get('/cuenta')->assertOk()->assertSee(__('portal.access_request.title'));
        $this->actingAs($user)->get('/usuarios')->assertRedirect(route('account.status'));
    }

    public function test_active_user_sees_only_assigned_subsystems_in_the_launcher(): void
    {
        $user = User::factory()->active()->create();
        $user->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);

        $this->actingAs($user)->get('/inicio')->assertOk()
            ->assertSee('Tickets')
            ->assertDontSee('Seguimiento de estaciones');
    }

    public function test_launcher_ignores_access_rows_for_subsystems_missing_from_the_catalog(): void
    {
        $user = User::factory()->active()->create();
        $user->accesos()->create(['subsistema' => 'inexistente', 'rol' => 'x']);

        $this->actingAs($user)->get('/inicio')->assertOk()->assertSee(__('portal.launcher.empty'));
    }

    public function test_regular_users_cannot_see_or_decide_on_requests(): void
    {
        $user = User::factory()->active()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->get('/usuarios')->assertForbidden();
        $this->actingAs($user)->get('/usuarios/'.$target->id)->assertForbidden();
        $this->actingAs($user)->post("/usuarios/{$target->id}/aprobar", ['accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado']]])
            ->assertForbidden();
        $this->actingAs($user)->post("/usuarios/{$target->id}/rechazar", ['reason' => 'no'])->assertForbidden();

        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
    }

    public function test_approver_approves_with_subsystem_access_and_the_decision_is_audited(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $target = User::factory()->create();

        $this->asVerified($jefe)->post("/usuarios/{$target->id}/aprobar", [
            'accesos' => [
                'tickets' => ['activo' => 1, 'rol' => 'coordinador', 'team_ids' => [1]],
                'dashboard' => ['rol' => 'estacion'],
            ],
        ])->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertSame(UserStatus::Active, $target->status);
        $this->assertSame($jefe->id, $target->decided_by);
        $this->assertDatabaseHas('usuario_subsistema', ['user_id' => $target->id, 'subsistema' => 'tickets', 'rol' => 'coordinador']);
        $this->assertDatabaseMissing('usuario_subsistema', ['user_id' => $target->id, 'subsistema' => 'dashboard']);
        $this->assertTrue(Activity::where('event', 'approved')->where('causer_id', $jefe->id)->exists());
    }

    public function test_approval_requires_a_valid_subsystem_and_role(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->create();

        $this->asVerified($admin)->post("/usuarios/{$target->id}/aprobar", ['accesos' => ['tickets' => ['rol' => 'empleado']]])
            ->assertSessionHasErrors('accesos');
        $this->asVerified($admin)->post("/usuarios/{$target->id}/aprobar", ['accesos' => ['tickets' => ['activo' => 1, 'rol' => 'dios']]])
            ->assertSessionHasErrors('accesos.tickets.rol');
        $this->asVerified($admin)->post("/usuarios/{$target->id}/aprobar", ['accesos' => ['inventado' => ['activo' => 1, 'rol' => 'x']]])
            ->assertSessionHasErrors('accesos');

        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
        $this->assertDatabaseCount('usuario_subsistema', 0);
    }

    public function test_approver_cannot_decide_on_own_account_or_on_non_pending_accounts(): void
    {
        $admin = User::factory()->administrador()->create();
        $active = User::factory()->active()->create();
        $payload = ['accesos' => ['tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]]]];

        $this->asVerified($admin)->post("/usuarios/{$admin->id}/aprobar", $payload)->assertSessionHasErrors('business');
        $this->asVerified($admin)->post("/usuarios/{$active->id}/aprobar", $payload)->assertSessionHasErrors('business');
        $this->asVerified($admin)->post("/usuarios/{$active->id}/rechazar", ['reason' => 'motivo'])->assertSessionHasErrors('business');

        $this->assertSame(UserStatus::Active, $active->fresh()->status);
    }

    public function test_rejection_requires_a_reason_and_deactivates_the_account(): void
    {
        $admin = User::factory()->administrador()->create();
        $target = User::factory()->create();

        $this->asVerified($admin)->post("/usuarios/{$target->id}/rechazar", [])->assertSessionHasErrors('reason');
        $this->asVerified($admin)->post("/usuarios/{$target->id}/rechazar", ['reason' => 'No pertenece a la Zona'])
            ->assertRedirect(route('users.index'));

        $this->assertSame(UserStatus::Inactive, $target->fresh()->status);
        $this->assertTrue(Activity::where('event', 'rejected')->exists());
    }

    public function test_inactive_approver_cannot_approve(): void
    {
        $inactive = User::factory()->inactive()->withTwoFactor()->state(['portal_role' => 'administrador'])->create();
        $target = User::factory()->create();

        $this->actingAs($inactive)->get('/usuarios')->assertRedirect(route('account.status'));
        $this->assertFalse($inactive->canApprove());
        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
    }

    public function test_profile_updates_only_the_name_and_never_the_email(): void
    {
        $user = User::factory()->active()->create(['email' => 'fijo@example.com']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Nuevo Nombre', 'email' => 'otro@example.com'])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Nuevo Nombre', $user->name);
        $this->assertSame('fijo@example.com', $user->email);
    }

    public function test_create_admin_command_creates_an_active_approver(): void
    {
        $this->artisan('portal:create-admin', ['email' => 'Jefe@Example.com', '--name' => 'Jefe Zona', '--role' => 'jefe_zona'])
            ->expectsQuestion('Contraseña', self::PASSWORD)
            ->assertSuccessful();

        $user = User::where('email', 'jefe@example.com')->firstOrFail();
        $this->assertTrue($user->canApprove());
    }

    public function test_create_admin_command_rejects_weak_passwords_and_bad_roles(): void
    {
        $this->artisan('portal:create-admin', ['email' => 'a@example.com', '--name' => 'A B'])
            ->expectsQuestion('Contraseña', 'debil')
            ->assertFailed();
        $this->artisan('portal:create-admin', ['email' => 'b@example.com', '--name' => 'B C', '--role' => 'usuario'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_guests_are_sent_to_login_for_protected_pages(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
        $this->get('/usuarios')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
    }
}
