<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarioTipo;
use App\Models\CalendarioEvento;
use App\Models\User;
use App\Notifications\SubsystemNotice;
use App\Services\TwoFactorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Calendario oficial: consulta para todas las cuentas, edición solo de administración, zona de Cancún y avisos. */
class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    /** @return array<string, mixed> */
    private function datos(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'evento',
            'titulo' => 'Reunión de coordinación',
            'lugar' => 'Sala Cancún',
            'fecha_inicio' => '2026-12-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:30',
        ], $extra);
    }

    public function test_any_active_account_can_view_the_calendar(): void
    {
        $user = User::factory()->active()->create();
        CalendarioEvento::create([
            'tipo' => 'evento', 'titulo' => 'Visita de obra', 'inicia_at' => '2026-12-10 15:00:00', 'todo_el_dia' => false,
        ]);

        $this->asVerified($user)->get(route('calendar.index', ['mes' => '2026-12']))
            ->assertOk()->assertSee('Visita de obra')->assertDontSee(route('calendar.events.create'));
    }

    public function test_regular_users_cannot_create_edit_or_delete(): void
    {
        $user = User::factory()->active()->create();
        $evento = CalendarioEvento::create(['tipo' => 'evento', 'titulo' => 'X', 'inicia_at' => now()->addDay(), 'todo_el_dia' => false]);

        $this->asVerified($user)->get(route('calendar.events.create'))->assertForbidden();
        $this->asVerified($user)->post(route('calendar.events.store'), $this->datos())->assertForbidden();
        $this->asVerified($user)->get(route('calendar.events.edit', $evento))->assertForbidden();
        $this->asVerified($user)->put(route('calendar.events.update', $evento), $this->datos())->assertForbidden();
        $this->asVerified($user)->delete(route('calendar.events.destroy', $evento))->assertForbidden();

        $this->assertSame(1, CalendarioEvento::count());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('calendar.index'))->assertRedirect(route('login'));
    }

    public function test_admin_creates_an_entry_stored_in_utc_from_cancun_time(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->post(route('calendar.events.store'), $this->datos())
            ->assertRedirect(route('calendar.index', ['mes' => '2026-12']));

        $evento = CalendarioEvento::firstOrFail();
        // Cancún es UTC-5 todo el año: 10:00 local = 15:00 UTC.
        $this->assertSame('2026-12-10 15:00:00', $evento->inicia_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-10 16:30:00', $evento->termina_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($admin->id, $evento->creado_por);
        $this->assertSame(CalendarioTipo::Evento, $evento->tipo);
    }

    public function test_inhabil_is_always_all_day_and_needs_no_time(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->post(route('calendar.events.store'), [
            'tipo' => 'inhabil', 'titulo' => 'Navidad', 'fecha_inicio' => '2026-12-25',
        ])->assertSessionHasNoErrors();

        $evento = CalendarioEvento::firstOrFail();
        $this->assertTrue($evento->todo_el_dia);
        $this->assertSame('2026-12-25 05:00:00', $evento->inicia_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(['2026-12-25'], $evento->diasLocales());
    }

    public function test_a_timed_entry_requires_start_time_and_end_cannot_precede_start(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->post(route('calendar.events.store'), $this->datos(['hora_inicio' => null]))
            ->assertSessionHasErrors('hora_inicio');

        $this->asVerified($admin)->post(route('calendar.events.store'), $this->datos(['hora_inicio' => '12:00', 'hora_fin' => '09:00']))
            ->assertSessionHasErrors('hora_fin');

        $this->asVerified($admin)->post(route('calendar.events.store'), $this->datos(['fecha_fin' => '2026-12-09']))
            ->assertSessionHasErrors('fecha_fin');

        $this->assertSame(0, CalendarioEvento::count());
    }

    public function test_multiday_entries_appear_on_every_day_of_the_month_view(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->post(route('calendar.events.store'), [
            'tipo' => 'evento', 'titulo' => 'Capacitación regional', 'todo_el_dia' => '1',
            'fecha_inicio' => '2026-12-14', 'fecha_fin' => '2026-12-16',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2026-12-14', '2026-12-15', '2026-12-16'], CalendarioEvento::firstOrFail()->diasLocales());
    }

    public function test_publishing_notifies_active_accounts_and_audits(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();
        $ana = User::factory()->active()->create();
        $pendiente = User::factory()->create();

        $this->asVerified($admin)->post(route('calendar.events.store'), $this->datos());

        Notification::assertSentTo($ana, SubsystemNotice::class, fn (SubsystemNotice $n) => $n->source === 'calendario'
            && $n->url === '/calendario' && str_contains($n->title, 'Reunión de coordinación'));
        Notification::assertNotSentTo($pendiente, SubsystemNotice::class);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'calendario', 'event' => 'created', 'causer_id' => $admin->id]);
        $this->assertNotNull(CalendarioEvento::firstOrFail()->avisado_at);
    }

    public function test_editing_the_schedule_notifies_again_but_a_description_change_does_not(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();
        User::factory()->active()->create();
        $this->asVerified($admin)->post(route('calendar.events.store'), $this->datos());
        $evento = CalendarioEvento::firstOrFail();
        Notification::assertSentTimes(SubsystemNotice::class, 2); // admin (activo) + ana

        $this->travel(1)->minutes();
        $this->asVerified($admin)->put(route('calendar.events.update', $evento), $this->datos(['descripcion' => 'Llevar informe']));
        Notification::assertSentTimes(SubsystemNotice::class, 2);

        $this->travel(1)->minutes();
        $this->asVerified($admin)->put(route('calendar.events.update', $evento), $this->datos(['hora_inicio' => '12:00', 'hora_fin' => '13:00']));
        Notification::assertSentTimes(SubsystemNotice::class, 4);
    }

    public function test_deleting_a_future_published_entry_sends_a_cancellation(): void
    {
        Notification::fake();
        $admin = User::factory()->administrador()->create();
        $ana = User::factory()->active()->create();
        $evento = CalendarioEvento::create([
            'tipo' => 'evento', 'titulo' => 'Taller', 'inicia_at' => now()->addDays(5), 'todo_el_dia' => false,
        ]);
        $evento->forceFill(['avisado_at' => now()])->save();

        $this->asVerified($admin)->delete(route('calendar.events.destroy', $evento))->assertRedirect();

        $this->assertSoftDeleted($evento);
        Notification::assertSentTo($ana, SubsystemNotice::class, fn (SubsystemNotice $n) => str_starts_with($n->title, 'Cancelado'));
    }

    public function test_reminder_goes_out_once_for_tomorrows_entries_and_skips_inhabiles(): void
    {
        Notification::fake();
        $ana = User::factory()->active()->create();
        // Mañana 09:00 en Cancún.
        $manana = CarbonImmutable::now(config('app.display_timezone'))->addDay()->setTime(9, 0);
        $evento = CalendarioEvento::create(['tipo' => 'fecha_limite', 'titulo' => 'Entrega de reporte', 'inicia_at' => $manana->utc(), 'todo_el_dia' => false]);
        CalendarioEvento::create(['tipo' => 'inhabil', 'titulo' => 'Festivo', 'inicia_at' => $manana->startOfDay()->utc(), 'todo_el_dia' => true]);
        CalendarioEvento::create(['tipo' => 'evento', 'titulo' => 'Pasado mañana', 'inicia_at' => $manana->addDay()->utc(), 'todo_el_dia' => false]);

        $this->artisan('calendario:recordar')->assertSuccessful();
        $this->artisan('calendario:recordar')->assertSuccessful();

        Notification::assertSentToTimes($ana, SubsystemNotice::class, 1);
        Notification::assertSentTo($ana, SubsystemNotice::class, fn (SubsystemNotice $n) => str_starts_with($n->title, 'Mañana') && str_contains($n->title, 'Entrega de reporte'));
        $this->assertNotNull($evento->fresh()->recordado_at);
    }

    public function test_calendar_source_can_be_muted_in_the_notification_center(): void
    {
        $ana = User::factory()->active()->create();

        $this->asVerified($ana)->put(route('notifications.preferences'), ['active' => ['dashboard' => '1', 'tickets' => '1']])
            ->assertRedirect();

        $this->assertTrue($ana->fresh()->mutesPush('calendario'));
        $this->assertFalse($ana->fresh()->mutesPush('tickets'));
    }

    public function test_loader_imports_inhabiles_idempotently_and_rejects_bad_lines(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'inh');
        file_put_contents($ruta, "# lista oficial\n2026-12-25;Navidad\n2027-01-01;Año Nuevo\n");

        $this->artisan('calendario:cargar-inhabiles', ['archivo' => $ruta])->assertSuccessful();
        $this->artisan('calendario:cargar-inhabiles', ['archivo' => $ruta])->assertSuccessful();
        $this->assertSame(2, CalendarioEvento::where('tipo', 'inhabil')->count());

        file_put_contents($ruta, "2026-02-30;Imposible\n");
        $this->artisan('calendario:cargar-inhabiles', ['archivo' => $ruta])->assertFailed();
        @unlink($ruta);
    }
}
