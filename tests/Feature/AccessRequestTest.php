<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\NewUserPendingApproval;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/** Solicitud de acceso guiada: la cuenta pendiente declara puesto, funciones, sistemas y equipos; el aprobador la ve prellenada. */
class AccessRequestTest extends TestCase
{
    use FakesSubsystems, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSubsystems();
    }

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'puesto' => 'Auxiliar de estación',
            'ubicacion' => 'Estación Tulum',
            'telefono' => '984 123 4567',
            'funciones' => 'Capturo la asistencia diaria de la estación.',
            'accesos' => [
                'dashboard' => ['activo' => 1, 'rol' => 'estacion', 'estacion_id' => 5, 'area' => 'rh'],
                'tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]],
            ],
        ], $override);
    }

    public function test_pending_user_without_request_sees_the_wizard_with_the_catalog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cuenta')->assertOk()
            ->assertSee(__('portal.access_request.title'))
            ->assertSee('Soporte TI');
    }

    public function test_valid_request_is_saved_and_approvers_are_notified_once(): void
    {
        Notification::fake();
        User::factory()->jefeZona()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload())->assertRedirect(route('account.status'));

        $solicitud = SolicitudAcceso::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('enviada', $solicitud->estado);
        $this->assertSame('estacion', $solicitud->accesos['dashboard']['rol']);
        $this->assertSame([1], $solicitud->accesos['tickets']['team_ids']);
        Notification::assertSentTimes(NewUserPendingApproval::class, 1);

        // Corregirla no vuelve a notificar ni duplica la solicitud.
        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['puesto' => 'Jefe de turno']))->assertRedirect();
        $this->assertSame(1, SolicitudAcceso::where('user_id', $user->id)->count());
        $this->assertSame('Jefe de turno', $solicitud->fresh()->puesto);
        Notification::assertSentTimes(NewUserPendingApproval::class, 1);

        $this->actingAs($user)->get('/cuenta')->assertOk()->assertSee(__('portal.access_request.review_status_title'));
    }

    public function test_privileged_roles_cannot_be_requested(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['accesos' => ['dashboard' => ['rol' => 'administrador']]]))
            ->assertSessionHasErrors('accesos.dashboard.rol');
        $this->assertDatabaseCount('solicitudes_acceso', 0);
    }

    public function test_teams_and_stations_must_exist_in_the_live_catalog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['accesos' => ['tickets' => ['team_ids' => [999]]]]))
            ->assertSessionHasErrors('accesos.tickets.team_ids');
        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['accesos' => ['dashboard' => ['estacion_id' => 999]]]))
            ->assertSessionHasErrors('accesos.dashboard.estacion_id');
    }

    public function test_at_least_one_system_and_the_text_fields_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['accesos' => ['dashboard' => ['activo' => 0], 'tickets' => ['activo' => 0]]]))
            ->assertSessionHasErrors('accesos');
        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['puesto' => '', 'funciones' => 'corto']))
            ->assertSessionHasErrors(['puesto', 'funciones']);
    }

    public function test_active_users_cannot_send_a_request(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('solicitudes_acceso', 0);
    }

    public function test_approver_sees_the_request_prefilled_and_approving_marks_it(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload());

        $page = $this->actingAs($jefe)->withSession([TwoFactorService::SESSION_KEY => $jefe->id])->get("/usuarios/{$user->id}");
        $page->assertOk()->assertSee('Auxiliar de estación')->assertSee('Capturo la asistencia diaria');
        $this->assertMatchesRegularExpression('/name="accesos\[tickets\]\[team_ids\]\[\]" value="1" checked/', $page->getContent());

        $this->actingAs($jefe)->withSession([TwoFactorService::SESSION_KEY => $jefe->id])->post("/usuarios/{$user->id}/aprobar", [
            'accesos' => [
                'dashboard' => ['activo' => 1, 'rol' => 'estacion', 'estacion_id' => 5, 'area' => 'rh'],
                'tickets' => ['activo' => 1, 'rol' => 'empleado', 'team_ids' => [1]],
            ],
        ])->assertRedirect();

        $this->assertSame('aprobada', $user->solicitudAcceso()->first()->estado);
    }

    public function test_catalog_down_still_accepts_a_free_text_request(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('', 500)]);
        \Illuminate\Support\Facades\Cache::flush();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/cuenta/solicitud', $this->payload(['accesos' => [
            'dashboard' => ['estacion_id' => '', 'area' => '', 'detalle' => 'Estación Tulum, RH'],
            'tickets' => ['team_ids' => [], 'detalle' => 'Equipo de RH'],
        ]]))->assertRedirect(route('account.status'));

        $this->assertSame('Equipo de RH', SolicitudAcceso::firstOrFail()->accesos['tickets']['detalle']);
    }
}
