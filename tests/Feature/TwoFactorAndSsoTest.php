<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ecosystem\EcosystemSignature;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAndSsoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a-very-long-shared-secret-for-tests-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosistema.sso.secreto' => self::SECRET,
            'ecosistema.urls.tickets' => 'https://tickets.test',
            'ecosistema.urls.dashboard' => 'https://dashboard.test',
        ]);
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    /** @return array<string, mixed> */
    private function decode(string $code): array
    {
        [$payload, $signature] = explode('.', $code);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, self::SECRET, true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($expected, $signature), 'firma inválida');

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    private function codeFrom(string $location): string
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return (string) ($query['codigo'] ?? '');
    }

    // --- 2FA ---

    public function test_approvers_without_2fa_are_sent_to_setup_and_regular_users_are_not(): void
    {
        $admin = User::factory()->active()->state(['portal_role' => 'administrador'])->create();
        $regular = User::factory()->active()->create();

        $this->actingAs($admin)->get('/inicio')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($regular)->get('/inicio')->assertOk();
    }

    public function test_setup_confirms_with_a_valid_code_and_shows_recovery_codes_once(): void
    {
        $admin = User::factory()->active()->state(['portal_role' => 'administrador'])->create();

        $this->actingAs($admin)->get('/two-factor/setup')->assertOk()->assertSee('<svg', false);
        $secret = $admin->fresh()->two_factor_secret;
        $this->assertNotNull($secret);
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);

        $this->actingAs($admin)->post('/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors('code');

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->actingAs($admin)->post('/two-factor/setup', ['code' => $code])->assertRedirect(route('two-factor.recovery'));

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $this->assertTrue(Hash::check('x', Hash::make('x'))); // los códigos se guardan hasheados:
        $this->assertStringStartsWith('$2y$', $admin->two_factor_recovery_codes[0]);

        $this->get('/two-factor/recovery')->assertOk()->assertSee('-');
        $this->get('/two-factor/recovery')->assertRedirect(route('dashboard'));
    }

    public function test_challenge_accepts_a_fresh_code_once_and_rejects_a_replay(): void
    {
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $admin = User::factory()->administrador()->create(['two_factor_secret' => $secret]);

        $this->actingAs($admin)->get('/inicio')->assertRedirect(route('two-factor.challenge'));
        $this->actingAs($admin)->post('/two-factor/challenge', ['code' => '123456'])->assertSessionHasErrors('code');

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->actingAs($admin)->post('/two-factor/challenge', ['code' => $code])->assertRedirect();
        $this->actingAs($admin)->get('/inicio')->assertOk();

        // Mismo código en otra sesión: ya se usó.
        $this->flushSession();
        $this->actingAs($admin->fresh())->post('/two-factor/challenge', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_recovery_code_works_once(): void
    {
        $admin = User::factory()->administrador()->create();
        $admin->forceFill(['two_factor_recovery_codes' => [Hash::make('abcdefghij')]])->save();

        $this->actingAs($admin)->post('/two-factor/challenge', ['recovery_code' => 'abcde-fghij'])->assertRedirect();
        $this->assertSame([], $admin->fresh()->two_factor_recovery_codes);

        $this->flushSession();
        $this->actingAs($admin->fresh())->post('/two-factor/challenge', ['recovery_code' => 'abcde-fghij'])
            ->assertSessionHasErrors('code');
    }

    public function test_pending_accounts_cannot_reach_2fa_or_the_launcher(): void
    {
        $pending = User::factory()->create();

        $this->actingAs($pending)->get('/two-factor/setup')->assertRedirect(route('account.status'));
    }

    // --- Inicio único ---

    public function test_portal_signs_a_login_code_for_a_subsystem_the_user_can_access(): void
    {
        $user = User::factory()->active()->create(['email' => 'Ana@Example.com']);
        $user->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);
        $state = str_repeat('a1', 20);

        $response = $this->actingAs($user)->get('/ecosistema/sso?para=tickets&estado='.$state);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://tickets.test/ecosistema/entrar?codigo=', $location);

        $payload = $this->decode(urldecode($this->codeFrom($location)));
        $this->assertSame('portal', $payload['iss']);
        $this->assertSame('tickets', $payload['aud']);
        $this->assertSame('entrar', $payload['act']);
        $this->assertSame('ana@example.com', $payload['email']);
        $this->assertSame($state, $payload['state']);
        $this->assertTrue($payload['lnk']);
        $this->assertFalse($payload['mfa']);
        $this->assertLessThanOrEqual(60, $payload['exp'] - time());
    }

    public function test_code_reports_mfa_when_the_portal_session_passed_2fa(): void
    {
        $admin = User::factory()->administrador()->create();
        $admin->accesos()->create(['subsistema' => 'dashboard', 'rol' => 'jefe_zona']);

        $response = $this->verified($admin)->get('/ecosistema/sso?para=dashboard');

        $payload = $this->decode(urldecode($this->codeFrom($response->headers->get('Location'))));
        $this->assertTrue($payload['mfa']);
    }

    public function test_no_code_without_access_or_without_a_complete_session(): void
    {
        $noAccess = User::factory()->active()->create();
        $admin = User::factory()->administrador()->create();
        $admin->accesos()->create(['subsistema' => 'tickets', 'rol' => 'administrador']);
        $pending = User::factory()->create();
        $pending->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);

        $none = 'https://tickets.test/ecosistema/entrar?sin_sesion=1';

        $this->get('/ecosistema/sso?para=tickets')->assertRedirect($none);
        $this->actingAs($noAccess)->get('/ecosistema/sso?para=tickets')->assertRedirect($none);
        // Un aprobador que aún no pasó el 2FA en esta sesión no comparte nada.
        $this->actingAs($admin)->get('/ecosistema/sso?para=tickets')->assertRedirect($none);
        $this->actingAs($pending)->get('/ecosistema/sso?para=tickets')->assertRedirect($none);
    }

    public function test_sso_request_rejects_unknown_targets_and_bad_state(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user)->get('/ecosistema/sso?para=otro')->assertSessionHasErrors('para');
        $this->actingAs($user)->get('/ecosistema/sso?para=tickets&estado=corto')->assertSessionHasErrors('estado');
    }

    public function test_sso_is_off_without_a_configured_secret(): void
    {
        config(['ecosistema.sso.secreto' => null]);
        $user = User::factory()->active()->create();
        $user->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);

        $this->actingAs($user)->get('/ecosistema/sso?para=tickets')
            ->assertRedirect('https://tickets.test/ecosistema/entrar?sin_sesion=1');
    }

    // --- Cierre único ---

    /** Código de "salir" firmado por un subsistema (cambia temporalmente la identidad del emisor). */
    private function leaveCodeFrom(string $issuer, string $email): string
    {
        config(['ecosistema.sistema' => $issuer]);
        $code = (new EcosystemSignature)->sign('portal', EcosystemSignature::LEAVE, $email, false);
        config(['ecosistema.sistema' => 'portal']);

        return $code;
    }

    public function test_leave_logs_out_the_same_person_and_chains_the_other_subsystems(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@example.com']);
        $user->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);
        $user->accesos()->create(['subsistema' => 'dashboard', 'rol' => 'estacion']);

        $response = $this->actingAs($user)
            ->get('/ecosistema/salir?codigo='.urlencode($this->leaveCodeFrom('tickets', 'ana@example.com')));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        // Tickets originó el cierre: solo queda Dashboard en la cadena.
        $cookie = $response->getCookie('portal_salida');
        $this->assertNotNull($cookie);
        $this->assertSame(['dashboard'], json_decode($cookie->getValue(), true)['pending']);
    }

    public function test_leave_ignores_a_code_for_a_different_person_or_a_forged_one(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@example.com']);

        $this->actingAs($user)->get('/ecosistema/salir?codigo='.urlencode($this->leaveCodeFrom('tickets', 'otra@example.com')))
            ->assertRedirect(route('login'));
        $this->assertAuthenticatedAs($user);

        $this->get('/ecosistema/salir?codigo=abc.def')->assertRedirect(route('login'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_leave_code_cannot_be_reused(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@example.com']);
        $code = $this->leaveCodeFrom('tickets', 'ana@example.com');

        $this->actingAs($user)->get('/ecosistema/salir?codigo='.urlencode($code));
        $this->assertGuest();

        $this->actingAs($user)->get('/ecosistema/salir?codigo='.urlencode($code));
        $this->assertAuthenticatedAs($user);
    }

    public function test_portal_logout_starts_the_chain_through_the_users_subsystems(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@example.com']);
        $user->accesos()->create(['subsistema' => 'tickets', 'rol' => 'empleado']);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        // El login siguiente salta al subsistema pendiente con un código "salir" firmado.
        // withCookie() cifra el valor como lo haría el navegador al devolverla.
        $cookie = $response->getCookie('portal_salida');
        $next = $this->withCookie('portal_salida', $cookie->getValue())->get('/login');

        $location = $next->headers->get('Location');
        $this->assertStringStartsWith('https://tickets.test/ecosistema/salir?codigo=', $location);
        $payload = $this->decode(urldecode($this->codeFrom($location)));
        $this->assertSame(['iss' => 'portal', 'aud' => 'tickets', 'act' => 'salir', 'email' => 'ana@example.com'],
            array_intersect_key($payload, array_flip(['iss', 'aud', 'act', 'email'])));
    }

    public function test_logout_without_subsystems_goes_home(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
    }
}
