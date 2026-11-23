<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Mis sistemas: la tarjeta de Temayin AI aparece para toda cuenta activa, con o sin sistemas asignados, sin enlace. */
class LauncherTest extends TestCase
{
    use RefreshDatabase;

    public function test_temayin_card_shows_for_an_active_account_without_systems(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Temayin AI')
            ->assertSee('Próximamente')
            ->assertSee(__('portal.launcher.empty'));
    }

    /** El saludo sigue la hora de Cancún (UTC-5), no la del servidor (UTC): a las 10:00 locales es "Buenos días". */
    public function test_the_greeting_follows_the_local_time_of_cancun_not_utc(): void
    {
        $user = User::factory()->active()->create();
        $cases = [
            ['2026-11-23 15:00:00', 'Buenos días'],    // 10:00 en Cancún
            ['2026-11-23 16:59:00', 'Buenos días'],    // 11:59 en Cancún
            ['2026-11-23 17:00:00', 'Buenas tardes'],  // 12:00 en Cancún
            ['2026-11-23 23:59:00', 'Buenas tardes'],  // 18:59 en Cancún
            ['2026-11-24 00:00:00', 'Buenas noches'],  // 19:00 en Cancún
        ];

        foreach ($cases as [$utc, $greeting]) {
            $this->travelTo(Carbon::parse($utc, 'UTC'));

            $response = $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id])->get(route('dashboard'));

            $response->assertOk();
            $this->assertStringContainsString($greeting.', ', $response->getContent(), 'A las '.$utc.' UTC debía saludar: '.$greeting);
        }
    }
}
