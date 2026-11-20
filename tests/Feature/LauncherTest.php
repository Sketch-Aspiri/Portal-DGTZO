<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
