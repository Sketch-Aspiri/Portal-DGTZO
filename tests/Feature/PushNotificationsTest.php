<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SubsystemNotice;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/**
 * PWA del portal y push general: los sistemas (Dashboard, Tickets) mandan avisos por el puente firmado, el portal los
 * guarda en el centro de notificaciones y los entrega por Web Push; cada persona gestiona sus dispositivos y puede
 * silenciar el push de un sistema.
 */
class PushNotificationsTest extends TestCase
{
    use FakesSubsystems, RefreshDatabase;

    private const PATH = '/interno/subsistemas/notificaciones';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSubsystems();
    }

    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    /** @return array<string, string> */
    private function signed(string $body, string $secret, string $system): array
    {
        $date = (string) now()->getTimestamp();
        $nonce = Str::random(40);
        $canonical = implode("\n", ['POST', self::PATH, '', $system, $date, $nonce, hash('sha256', $body)]);

        return ['X-Puente-Sistema' => $system, 'X-Puente-Fecha' => $date, 'X-Puente-Nonce' => $nonce, 'X-Puente-Firma' => hash_hmac('sha256', $canonical, $secret)];
    }

    /** @param  array<string, mixed>  $overrides */
    private function notice(array $overrides = [], string $system = 'dashboard', ?string $secret = null): TestResponse
    {
        $body = json_encode($overrides + [
            'correos' => ['ana@ejemplo.test'],
            'titulo' => 'Asistencia pendiente',
            'cuerpo' => 'Tulum aún no captura la asistencia de hoy.',
            'url' => 'https://dashboard.test/asistencia/captura',
            'ref' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR);
        $secret ??= $system === 'dashboard' ? self::DASHBOARD_SECRET : self::TICKETS_SECRET;
        $headers = $this->signed($body, $secret, $system) + ['CONTENT_TYPE' => 'application/json', 'Accept' => 'application/json'];

        return $this->call('POST', self::PATH, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    // --- Puente: recepción de avisos -------------------------------------

    public function test_a_signed_notice_from_the_dashboard_reaches_an_active_account(): void
    {
        $ana = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->notice()->assertOk()->assertExactJson(['recipients' => 1]);

        $stored = $ana->notifications()->firstOrFail();
        $this->assertSame(SubsystemNotice::TYPE, $stored->data['type']);
        $this->assertSame('dashboard', $stored->data['source']);
        $this->assertSame('Asistencia pendiente', $stored->data['title']);
        $this->assertSame('https://dashboard.test/asistencia/captura', $stored->data['url']);
    }

    public function test_tickets_can_also_send_notices(): void
    {
        User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->notice(['url' => 'https://tickets.test/tickets/5'], 'tickets')->assertOk()->assertExactJson(['recipients' => 1]);
    }

    public function test_inactive_pending_and_unknown_accounts_are_skipped_without_revealing_which(): void
    {
        User::factory()->inactive()->create(['email' => 'baja@ejemplo.test']);
        User::factory()->create(['email' => 'pendiente@ejemplo.test']);

        $this->notice(['correos' => ['baja@ejemplo.test', 'pendiente@ejemplo.test', 'nadie@ejemplo.test']])
            ->assertOk()->assertExactJson(['recipients' => 0]);

        $this->assertSame(0, \DB::table('notifications')->count());
    }

    public function test_emails_are_matched_ignoring_case(): void
    {
        $ana = User::factory()->active()->create(['email' => 'Ana@Ejemplo.test']);

        $this->notice(['correos' => ['ANA@ejemplo.test']])->assertOk()->assertExactJson(['recipients' => 1]);
        $this->assertSame(1, $ana->notifications()->count());
    }

    public function test_the_same_ref_does_not_notify_twice(): void
    {
        $ana = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->notice(['ref' => 'lote-0001'])->assertExactJson(['recipients' => 1]);
        $this->notice(['ref' => 'lote-0001'])->assertExactJson(['recipients' => 0]);

        $this->assertSame(1, $ana->notifications()->count());
    }

    public function test_unsigned_tampered_and_unknown_systems_are_rejected(): void
    {
        User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->postJson(self::PATH, ['correos' => ['ana@ejemplo.test']])->assertStatus(404);
        $this->notice([], 'dashboard', 'secreto-equivocado-de-prueba-0123456789abcdef')->assertStatus(401);
        $this->notice([], 'intruso')->assertStatus(404);
        $this->assertSame(0, \DB::table('notifications')->count());
    }

    public function test_the_notice_is_validated(): void
    {
        User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->notice(['titulo' => str_repeat('a', 61)])->assertStatus(422);
        $this->notice(['cuerpo' => str_repeat('a', 121)])->assertStatus(422);
        $this->notice(['titulo' => "Hola\u{202E}mundo"])->assertStatus(422);
        $this->notice(['correos' => []])->assertStatus(422);
        $this->notice(['correos' => array_fill(0, 51, 'x@ejemplo.test')])->assertStatus(422);
        $this->notice(['url' => 'https://malicioso.example/tickets'])->assertStatus(422);
        $this->notice(['url' => 'https://tickets.test/ecosistema/salir'])->assertStatus(422);
        $this->notice(['url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->notice(['ref' => 'con espacios'])->assertStatus(422);
    }

    public function test_a_muted_system_only_leaves_the_notice_in_the_center_without_push(): void
    {
        $ana = User::factory()->active()->create();
        $ana->notificationPreferences()->create(['subsistema' => 'dashboard', 'muted' => true]);
        $notice = new SubsystemNotice('T', 'C', 'https://dashboard.test/inicio', 'dashboard');

        $this->assertSame(['database'], $notice->via($ana));
        $this->assertSame(['database', WebPushChannel::class], (new SubsystemNotice('T', 'C', 'https://tickets.test/inicio', 'tickets'))->via($ana));
    }

    public function test_the_push_message_is_built_with_title_body_icon_and_target(): void
    {
        $ana = User::factory()->active()->create();
        $message = (new SubsystemNotice('Título', 'Cuerpo', 'https://dashboard.test/asistencia/captura', 'dashboard'))->toWebPush($ana, new SubsystemNotice('x', 'y', '/inicio', 'dashboard'));
        $array = $message->toArray();

        $this->assertSame('Título', $array['title']);
        $this->assertSame('Cuerpo', $array['body']);
        $this->assertSame('/icons/icon-192.png', $array['icon']);
        $this->assertSame('https://dashboard.test/asistencia/captura', $array['data']['url']);
    }

    public function test_active_recipients_are_notified_through_the_queue_facade(): void
    {
        Notification::fake();
        $ana = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);

        $this->notice()->assertOk();

        Notification::assertSentTo($ana, SubsystemNotice::class);
    }

    // --- Suscripciones push -----------------------------------------------

    /** @return array<string, mixed> */
    private function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey_p256dh-0123456789abcdef', 'auth' => 'authKey-0123456789'], 'contentEncoding' => 'aes128gcm'];
    }

    public function test_guests_cannot_manage_subscriptions(): void
    {
        $this->postJson(route('push.subscribe'), $this->subscription())->assertStatus(401);
    }

    public function test_a_person_subscribes_and_unsubscribes_a_device(): void
    {
        $ana = User::factory()->active()->create();

        $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription())->assertCreated();
        $this->assertSame(1, $ana->pushSubscriptions()->count());

        $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription())->assertOk();
        $this->assertSame(1, $ana->pushSubscriptions()->count());

        $this->asVerified($ana)->postJson(route('push.unsubscribe'), ['endpoint' => $this->subscription()['endpoint']])->assertOk();
        $this->assertSame(0, $ana->pushSubscriptions()->count());
    }

    public function test_only_browser_push_services_over_https_are_accepted(): void
    {
        $ana = User::factory()->active()->create();

        foreach (['http://fcm.googleapis.com/x', 'https://interno.local/x', 'https://fcm.googleapis.com:8443/x', 'https://user@fcm.googleapis.com/x', 'https://evil.example/fcm.googleapis.com'] as $endpoint) {
            $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription($endpoint))->assertStatus(422);
        }

        $this->assertSame(0, $ana->pushSubscriptions()->count());
    }

    public function test_the_device_limit_is_enforced(): void
    {
        $ana = User::factory()->active()->create();

        foreach (range(1, 10) as $n) {
            $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription('https://fcm.googleapis.com/fcm/send/d'.$n))->assertCreated();
        }

        $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription('https://fcm.googleapis.com/fcm/send/d11'))->assertStatus(422);
    }

    public function test_one_person_cannot_remove_anothers_subscription(): void
    {
        $ana = User::factory()->active()->create();
        $beto = User::factory()->active()->create();
        $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription())->assertCreated();

        $this->asVerified($beto)->postJson(route('push.unsubscribe'), ['endpoint' => $this->subscription()['endpoint']])->assertOk();

        $this->assertSame(1, $ana->pushSubscriptions()->count());
    }

    // --- Centro de notificaciones ----------------------------------------

    private function notificationFor(User $user, string $url = 'https://tickets.test/tickets/7'): string
    {
        $user->notify(new SubsystemNotice('Nueva asignación', 'Te asignaron un ticket.', $url, 'tickets'));

        return (string) $user->notifications()->latest()->firstOrFail()->id;
    }

    public function test_the_center_requires_a_signed_in_active_account(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_the_center_lists_the_notices_of_every_system_with_their_origin(): void
    {
        $ana = User::factory()->active()->create();
        $this->notificationFor($ana);
        $ana->notify(new SubsystemNotice('Cumpleaños de hoy', 'Hoy cumple Luis.', 'https://dashboard.test/cumpleanos', 'dashboard'));

        $this->asVerified($ana)->get(route('notifications.index'))
            ->assertOk()->assertSee('Nueva asignación')->assertSee('Cumpleaños de hoy')->assertSee('Tickets')->assertSee('Dashboard');
    }

    public function test_the_center_never_shows_another_persons_notices(): void
    {
        $ana = User::factory()->active()->create();
        $beto = User::factory()->active()->create();
        $this->notificationFor($beto);

        $this->asVerified($ana)->get(route('notifications.index'))->assertOk()->assertDontSee('Nueva asignación');
    }

    public function test_opening_a_notice_marks_it_read_and_goes_to_its_target(): void
    {
        $ana = User::factory()->active()->create();
        $id = $this->notificationFor($ana);

        $this->asVerified($ana)->get(route('notifications.open', $id))->assertRedirect('https://tickets.test/tickets/7');

        $this->assertNotNull($ana->notifications()->firstOrFail()->read_at);
    }

    public function test_a_stored_url_that_is_no_longer_valid_goes_back_to_the_center(): void
    {
        $ana = User::factory()->active()->create();
        $id = $this->notificationFor($ana);
        $ana->notifications()->firstOrFail()->update(['data' => ['type' => SubsystemNotice::TYPE, 'title' => 'x', 'body' => 'y', 'url' => 'https://malicioso.example/x', 'source' => 'tickets']]);

        $this->asVerified($ana)->get(route('notifications.open', $id))->assertRedirect(route('notifications.index'));
    }

    public function test_one_person_cannot_open_anothers_notice(): void
    {
        $ana = User::factory()->active()->create();
        $beto = User::factory()->active()->create();
        $id = $this->notificationFor($beto);

        $this->asVerified($ana)->get(route('notifications.open', $id))->assertNotFound();
        $this->assertNull($beto->notifications()->firstOrFail()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $ana = User::factory()->active()->create();
        $this->notificationFor($ana);
        $this->notificationFor($ana);

        $this->asVerified($ana)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $ana->unreadNotifications()->count());
    }

    public function test_a_person_turns_a_system_off_and_on_with_the_switch(): void
    {
        $ana = User::factory()->active()->create();

        // Un interruptor encendido manda `active[sistema]`; el apagado no manda nada: ese sistema queda silenciado.
        $this->asVerified($ana)->put(route('notifications.preferences'), ['active' => ['dashboard' => '1']])
            ->assertRedirect(route('notifications.index'));
        $this->assertTrue($ana->mutesPush('tickets'));
        $this->assertFalse($ana->mutesPush('dashboard'));

        $this->asVerified($ana)->put(route('notifications.preferences'), ['active' => ['dashboard' => '1', 'tickets' => '1']])->assertRedirect();
        $this->assertFalse($ana->fresh()->mutesPush('tickets'));
        $this->assertFalse($ana->fresh()->mutesPush('dashboard'));

        $this->asVerified($ana)->put(route('notifications.preferences'), [])->assertRedirect();
        $this->assertTrue($ana->fresh()->mutesPush('dashboard'));
        $this->assertTrue($ana->fresh()->mutesPush('tickets'));
    }

    public function test_the_switch_saves_without_reloading_the_page(): void
    {
        $ana = User::factory()->active()->create();

        $this->asVerified($ana)->putJson(route('notifications.preferences'), ['active' => ['tickets' => '1']])
            ->assertOk()->assertExactJson(['ok' => true, 'active' => ['dashboard' => false, 'tickets' => true]]);

        $this->assertTrue($ana->mutesPush('dashboard'));
        $this->assertFalse($ana->mutesPush('tickets'));
    }

    public function test_the_systems_section_shows_a_clear_on_or_muted_state_with_one_switch_per_system(): void
    {
        $ana = User::factory()->active()->create();
        $ana->notificationPreferences()->create(['subsistema' => 'tickets', 'muted' => true]);

        $html = $this->asVerified($ana)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'role="switch"'));
        $this->assertStringContainsString(__('push.center.state_on'), $html);
        $this->assertStringContainsString(__('push.center.state_muted'), $html);
        $this->assertStringNotContainsString('name="muted[', $html);
        $this->assertMatchesRegularExpression('/name="active\[dashboard\]"[^>]*checked|checked[^>]*name="active\[dashboard\]"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/name="active\[tickets\]"[^>]*checked/s', $html);
    }

    public function test_the_home_shows_recent_notices_and_the_bell_counts_unread(): void
    {
        $ana = User::factory()->active()->create();
        $this->notificationFor($ana);

        $this->asVerified($ana)->get(route('dashboard'))->assertOk()->assertSee('Nueva asignación')->assertSee(route('notifications.index'));
    }

    // --- PWA y modo oscuro ---------------------------------------------------

    public function test_the_pwa_files_exist_and_the_head_links_them(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));

        $this->get('/login')->assertOk()->assertSee('manifest.json', false)->assertSee('apple-mobile-web-app-capable', false);
    }

    public function test_the_service_worker_only_opens_this_site_or_the_ecosystem(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'zonaorientetm.tech', 'tickets.zonaorientetm.tech', 'dashboard.zonaorientetm.tech'", $sw);
        $this->assertStringContainsString('ECOSYSTEM_HOSTS.includes(candidate.hostname)', $sw);
    }

    public function test_the_vapid_public_key_is_published_only_when_configured(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $this->get('/login')->assertDontSee('vapid-public-key', false);

        config(['webpush.vapid.public_key' => 'BClavePublicaDePrueba']);
        $this->get('/login')->assertSee('<meta name="vapid-public-key" content="BClavePublicaDePrueba">', false);
    }

    public function test_dark_mode_is_applied_before_painting_and_the_selector_is_offered(): void
    {
        $ana = User::factory()->active()->create();

        $this->get('/login')->assertOk()->assertSee("classList.toggle('dark'", false)->assertSee('selectorTema', false);
        $this->asVerified($ana)->get(route('notifications.index'))->assertOk()->assertSee('selectorTema', false);
    }

    // --- Cobertura -------------------------------------------------------------

    public function test_the_coverage_command_counts_devices_and_masks_emails(): void
    {
        $ana = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        User::factory()->active()->create(['email' => 'beto@ejemplo.test']);
        User::factory()->inactive()->create(['email' => 'baja@ejemplo.test']);
        $this->asVerified($ana)->postJson(route('push.subscribe'), $this->subscription())->assertCreated();

        $this->artisan('notificaciones:cobertura', ['--detalle' => true])
            ->expectsOutputToContain('Cuentas activas del portal: 2')
            ->expectsOutputToContain('Con al menos un dispositivo suscrito: 1')
            ->expectsOutputToContain('Sin dispositivo: 1')
            ->expectsOutputToContain('be***@ejemplo.test')
            ->doesntExpectOutputToContain('beto@ejemplo.test')
            ->assertSuccessful();
    }
}
