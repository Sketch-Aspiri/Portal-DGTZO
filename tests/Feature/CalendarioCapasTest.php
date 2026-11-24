<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/** Capas del calendario (cumpleaños y vacaciones del Dashboard): apagadas por omisión, solo lectura y a prueba de fallas. */
class CalendarioCapasTest extends TestCase
{
    use FakesSubsystems;
    use RefreshDatabase;

    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    private function fakeCapas(): void
    {
        $this->fakeSubsystems();
        config(['calendario.capas_dashboard' => true]);
        Http::fake([
            'https://dashboard.test/interno/portal/calendario/cumpleanos*' => Http::response(['cumpleanos' => [
                ['nombre' => 'Rosa Canul', 'estacion' => 'Tulum', 'fecha' => '2026-12-10'],
                ['nombre' => 'Fecha rota', 'estacion' => null, 'fecha' => '2026-13-45'],
            ]]),
            'https://dashboard.test/interno/portal/calendario/vacaciones*' => Http::response(['vacaciones' => [
                ['nombre' => 'Ana Uc', 'estacion' => 'Tulum', 'inicio' => '2026-12-14', 'fin' => '2026-12-16'],
            ]]),
        ]);
    }

    public function test_the_layers_are_off_by_default_and_the_dashboard_is_not_called(): void
    {
        $this->fakeSubsystems();
        config(['calendario.capas_dashboard' => false]);
        $user = User::factory()->active()->create();

        $this->asVerified($user)->get(route('calendar.index', ['mes' => '2026-12', 'capas' => ['cumpleanos']]))
            ->assertOk()->assertDontSee('Rosa Canul')->assertDontSee(__('portal.calendar.layers.title'));

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/calendario/'));
    }

    public function test_an_account_sees_birthdays_but_not_vacations_unless_allowed(): void
    {
        $this->fakeCapas();
        $user = User::factory()->active()->create();

        $this->asVerified($user)->get(route('calendar.index', ['mes' => '2026-12', 'capas' => ['cumpleanos', 'vacaciones']]))
            ->assertOk()->assertSee('Rosa Canul')->assertDontSee('Ana Uc')->assertDontSee('Fecha rota');

        config(['calendario.vacaciones_para_todos' => true]);
        Cache::flush();
        $this->asVerified($user)->get(route('calendar.index', ['mes' => '2026-12', 'capas' => ['vacaciones']]))
            ->assertOk()->assertSee('Ana Uc')->assertDontSee('Rosa Canul');
    }

    public function test_admins_see_vacations_and_the_request_is_signed_and_scoped_to_the_visible_grid(): void
    {
        $this->fakeCapas();
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->get(route('calendar.index', ['mes' => '2026-12', 'capas' => ['vacaciones']]))
            ->assertOk()->assertSee('Ana Uc');

        Http::assertSent(function (Request $r) {
            return str_starts_with($r->url(), 'https://dashboard.test/interno/portal/calendario/vacaciones')
                && $r->hasHeader('X-Puente-Firma') && $r['desde'] === '2026-11-30' && $r['hasta'] === '2027-01-03';
        });
    }

    public function test_layers_are_not_requested_unless_the_person_turns_them_on(): void
    {
        $this->fakeCapas();
        $admin = User::factory()->administrador()->create();

        $this->asVerified($admin)->get(route('calendar.index', ['mes' => '2026-12']))
            ->assertOk()->assertDontSee('Rosa Canul');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/calendario/'));
    }

    public function test_a_dashboard_failure_leaves_the_calendar_working(): void
    {
        $this->fakeSubsystems();
        config(['calendario.capas_dashboard' => true]);
        Http::fake(['https://dashboard.test/interno/portal/calendario/*' => Http::response('boom', 500)]);
        $user = User::factory()->active()->create();

        $this->asVerified($user)->get(route('calendar.index', ['mes' => '2026-12', 'capas' => ['cumpleanos']]))
            ->assertOk()->assertSee(__('portal.calendar.title'));
    }

    public function test_unknown_or_malformed_layers_are_ignored(): void
    {
        $this->fakeCapas();
        $user = User::factory()->active()->create();

        $this->asVerified($user)->get(route('calendar.index', ['mes' => '2026-12', 'capas' => [['x'], 'otra']]))->assertOk();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/calendario/'));
    }
}
