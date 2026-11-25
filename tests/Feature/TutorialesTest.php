<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TutorialesTest extends TestCase
{
    use RefreshDatabase;

    private string $ruta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruta = storage_path('framework/testing/guias-'.uniqid());
        $base = ['resumen' => 'Resumen', 'publicada' => '2026-01-01'];

        config(['guias.ruta' => $this->ruta, 'guias.guias' => [
            'acceso' => $base + ['sistema' => 'portal', 'titulo' => 'Guía pública', 'publica' => true],
            'perfil' => $base + ['sistema' => 'portal', 'titulo' => 'Guía de perfil'],
            'cuentas' => $base + ['sistema' => 'portal', 'titulo' => 'Guía de cuentas', 'roles' => ['administrador', 'jefe_zona']],
            'tickets-uso' => $base + ['sistema' => 'tickets', 'titulo' => 'Guía de tickets'],
            'tickets-admin' => $base + ['sistema' => 'tickets', 'titulo' => 'Guía de administración', 'roles' => ['administrador']],
            'asistencia' => $base + ['sistema' => 'dashboard', 'titulo' => 'Guía de asistencia', 'roles' => ['estacion']],
        ]]);

        foreach (config('guias.guias') as $slug => $guia) {
            File::ensureDirectoryExists("{$this->ruta}/{$guia['sistema']}");
            File::ensureDirectoryExists("{$this->ruta}/img/{$guia['sistema']}/{$slug}");
            File::put("{$this->ruta}/{$guia['sistema']}/{$slug}.md", "## Primer paso\n\nTexto de {$slug}.\n\n![Pantalla](01-pantalla.png)\n\n## Segundo paso\n\n<script>alert(1)</script>\n\n[malo](javascript:alert(2))\n");
            imagepng(imagecreatetruecolor(4, 2), "{$this->ruta}/img/{$guia['sistema']}/{$slug}/01-pantalla.png");
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->ruta);

        parent::tearDown();
    }

    private function entrar(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->getKey()]);
    }

    private function conAcceso(string $sistema, string $rol): User
    {
        $user = User::factory()->active()->create();
        $user->todosLosAccesos()->create(['subsistema' => $sistema, 'rol' => $rol]);

        return $user;
    }

    public function test_guests_only_see_public_guides(): void
    {
        $this->get(route('tutorials.index'))->assertOk()
            ->assertSee('Guía pública')->assertDontSee('Guía de perfil')->assertDontSee('Guía de tickets');
        $this->get(route('tutorials.show', 'acceso'))->assertOk()->assertSee('Texto de acceso.');
        $this->get(route('tutorials.image', ['acceso', '01-pantalla']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('tutorials.show', 'perfil'))->assertRedirect(route('login'));
        $this->get(route('tutorials.image', ['perfil', '01-pantalla']))->assertRedirect(route('login'));
    }

    public function test_pending_accounts_only_see_public_guides(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('tutorials.index'))->assertOk()->assertSee('Guía pública')->assertDontSee('Guía de perfil');
        $this->get(route('tutorials.show', 'perfil'))->assertRedirect(route('account.status'));
    }

    public function test_an_approver_without_two_factor_verification_only_sees_public_guides(): void
    {
        $this->actingAs(User::factory()->administrador()->create());

        $this->get(route('tutorials.index'))->assertOk()->assertDontSee('Guía de cuentas');
        $this->get(route('tutorials.show', 'cuentas'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_user_without_systems_sees_the_general_portal_guides(): void
    {
        $this->entrar(User::factory()->active()->create());

        $this->get(route('tutorials.index'))->assertOk()
            ->assertSee('Guía de perfil')->assertDontSee('Guía de cuentas')->assertDontSee('Guía de tickets');
        $this->get(route('tutorials.show', 'tickets-uso'))->assertNotFound();
        $this->get(route('tutorials.image', ['tickets-uso', '01-pantalla']))->assertNotFound();
        $this->get(route('tutorials.show', 'cuentas'))->assertNotFound();
    }

    public function test_guides_follow_the_system_and_role_of_the_account(): void
    {
        $this->entrar($this->conAcceso('tickets', 'empleado'))->get(route('tutorials.index'))
            ->assertSee('Guía de tickets')->assertDontSee('Guía de administración')->assertDontSee('Guía de asistencia');

        $this->entrar($this->conAcceso('dashboard', 'estacion'))->get(route('tutorials.index'))
            ->assertSee('Guía de asistencia')->assertDontSee('Guía de tickets');
    }

    public function test_a_revoked_access_hides_its_guides(): void
    {
        $user = $this->conAcceso('tickets', 'empleado');
        $user->todosLosAccesos()->first()->forceFill(['revocado_at' => now()])->save();

        $this->entrar($user)->get(route('tutorials.show', 'tickets-uso'))->assertNotFound();
    }

    public function test_portal_approvers_see_every_guide(): void
    {
        $this->entrar(User::factory()->jefeZona()->create())->get(route('tutorials.index'))
            ->assertSee('Guía de cuentas')->assertSee('Guía de administración')->assertSee('Guía de asistencia');
    }

    public function test_a_guide_renders_steps_and_protected_images_without_raw_html(): void
    {
        $this->get(route('tutorials.show', 'acceso'))->assertOk()
            ->assertSee('id="primer-paso"', false)
            ->assertSee('href="#segundo-paso"', false)
            ->assertSee('/tutoriales/acceso/img/01-pantalla?v=', false)
            // En producción nginx se queda con las direcciones terminadas en extensión de imagen.
            ->assertDontSee('/tutoriales/acceso/img/01-pantalla.png', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_unknown_guides_and_unsafe_file_names_are_not_found(): void
    {
        $this->get('/tutoriales/no-existe')->assertNotFound();
        $this->get('/tutoriales/acceso/img/no-existe')->assertNotFound();
        $this->get('/tutoriales/acceso/img/..%2F..%2Fperfil%2F01-pantalla')->assertNotFound();
        $this->get('/tutoriales/acceso/img/acceso.md')->assertNotFound();
    }
}
