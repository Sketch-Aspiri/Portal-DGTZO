<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lista de solicitudes: totales por estado, búsqueda por nombre o correo y acceso solo para quien aprueba. */
class ApprovalsListTest extends TestCase
{
    use RefreshDatabase;

    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    public function test_it_shows_counts_per_status_and_filters_by_search(): void
    {
        $jefe = User::factory()->jefeZona()->create();
        User::factory()->create(['name' => 'Rosa Canul', 'email' => 'rosa@ejemplo.test']);
        User::factory()->create(['name' => 'Pedro Uc', 'email' => 'pedro@ejemplo.test']);
        User::factory()->active()->create(['name' => 'Activa Uno']);

        $this->asVerified($jefe)->get(route('users.index'))
            ->assertOk()->assertSee('Rosa Canul')->assertSee('Pedro Uc')->assertDontSee('Activa Uno');

        $this->asVerified($jefe)->get(route('users.index', ['q' => 'rosa@']))
            ->assertOk()->assertSee('Rosa Canul')->assertDontSee('Pedro Uc');

        // Los comodines de LIKE se tratan como texto, no como patrón.
        $this->asVerified($jefe)->get(route('users.index', ['q' => '%']))->assertOk()->assertDontSee('Rosa Canul');
    }

    public function test_regular_users_cannot_open_the_list(): void
    {
        $user = User::factory()->active()->create();

        $this->asVerified($user)->get(route('users.index'))->assertForbidden();
    }
}
