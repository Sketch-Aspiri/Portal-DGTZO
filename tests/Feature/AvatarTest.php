<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PushAvatar;
use App\Models\User;
use App\Models\UsuarioSubsistema;
use App\Services\AvatarService;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/**
 * Foto de perfil única: se sube en el portal (reprocesada a JPEG cuadrado), se reparte a Tickets por el puente firmado,
 * un cambio hecho en Tickets llega por /interno/subsistemas/avatar (sin eco de vuelta) y la foto que ya había en
 * Tickets se hereda con portal:importar-fotos sin pisar una existente.
 */
class AvatarTest extends TestCase
{
    use FakesSubsystems, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fakeSubsystems();
    }

    private function png(int $w = 300, int $h = 200): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 10, 120, 90));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function asVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->id]);
    }

    private function withTicketsAccess(User $user): void
    {
        UsuarioSubsistema::create(['user_id' => $user->id, 'subsistema' => 'tickets', 'rol' => 'empleado', 'aprovisionamiento' => 'ok']);
    }

    /** @return array<string, string> */
    private function signed(string $method, string $path, string $body, string $secret = self::TICKETS_SECRET, string $system = 'tickets'): array
    {
        $date = (string) now()->getTimestamp();
        $nonce = Str::random(40);
        $canonical = implode("\n", [$method, $path, '', $system, $date, $nonce, hash('sha256', $body)]);

        return ['X-Puente-Sistema' => $system, 'X-Puente-Fecha' => $date, 'X-Puente-Nonce' => $nonce, 'X-Puente-Firma' => hash_hmac('sha256', $canonical, $secret)];
    }

    private function fromTickets(string $method, array $body, string $secret = self::TICKETS_SECRET): TestResponse
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signed($method, '/interno/subsistemas/avatar', $json, $secret) + ['CONTENT_TYPE' => 'application/json', 'Accept' => 'application/json'];

        return $this->call($method, '/interno/subsistemas/avatar', [], [], [], $this->transformHeadersToServerVars($headers), $json);
    }

    public function test_user_uploads_a_photo_that_is_reprocessed_and_pushed_to_tickets(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        $this->withTicketsAccess($user);

        $this->asVerified($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->createWithContent('foto.png', $this->png())])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $info = getimagesizefromstring((string) app(AvatarService::class)->bytes($user));
        $this->assertSame([256, 256, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);

        // La cola de pruebas es síncrona: el aviso a Tickets ya salió, firmado como "portal".
        Http::assertSent(fn (Request $r): bool => $r->method() === 'PUT'
            && $r->url() === 'https://tickets.test/interno/portal/avatar'
            && $r['email'] === 'ana@ejemplo.test'
            && ! empty($r['imagen']));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'dashboard.test') && str_contains($r->url(), 'avatar'));
    }

    public function test_removing_the_photo_is_pushed_as_a_delete(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        $this->withTicketsAccess($user);
        app(AvatarService::class)->setFromBytes($user, $this->png(), propagate: false);

        $this->asVerified($user)->delete('/profile/avatar')->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->avatar_path);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE' && $r->url() === 'https://tickets.test/interno/portal/avatar');
    }

    public function test_invalid_uploads_are_rejected(): void
    {
        $user = User::factory()->active()->create();

        $this->asVerified($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->createWithContent('x.png', '<svg onload=alert(1)>')])->assertSessionHasErrors('business');
        $this->asVerified($user)->post('/profile/avatar', [])->assertSessionHasErrors('avatar');
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_photos_are_only_served_to_signed_in_people_as_jpeg(): void
    {
        $owner = User::factory()->active()->create();
        $viewer = User::factory()->active()->create();
        app(AvatarService::class)->setFromBytes($owner, $this->png(), propagate: false);

        $this->get(route('avatars.show', $owner))->assertRedirect(route('login'));

        $response = $this->asVerified($viewer)->get(route('avatars.show', $owner));
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->asVerified($viewer)->get(route('avatars.show', $viewer))->assertNotFound();
    }

    public function test_profile_page_renders_for_the_user(): void
    {
        $user = User::factory()->active()->create(['name' => 'Ana Pérez']);
        $this->withTicketsAccess($user);

        $this->asVerified($user)->get(route('profile.edit'))->assertOk()->assertSee('Ana Pérez')->assertSee(__('portal.profile_page.photo_title'));
    }

    public function test_a_photo_from_tickets_is_stored_and_not_sent_back_to_tickets(): void
    {
        Queue::fake();
        $user = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        $this->withTicketsAccess($user);

        $this->fromTickets('PUT', ['email' => 'ANA@ejemplo.test', 'imagen' => base64_encode($this->png())])->assertOk()->assertJson(['estado' => 'ok']);

        $this->assertNotNull($user->refresh()->avatar_path);
        Queue::assertPushed(PushAvatar::class, fn (PushAvatar $job): bool => $job->origin === 'tickets');
    }

    public function test_the_push_skips_the_origin_subsystem(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        $this->withTicketsAccess($user);

        $this->fromTickets('PUT', ['email' => 'ana@ejemplo.test', 'imagen' => base64_encode($this->png())])->assertOk();

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'tickets.test/interno/portal/avatar'));
    }

    public function test_the_inbound_bridge_rejects_bad_signatures_unknown_systems_and_bad_images(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        $png = base64_encode($this->png());

        $this->fromTickets('PUT', ['email' => 'ana@ejemplo.test', 'imagen' => $png], 'otro-secreto-distinto-0123456789abcdef0123')->assertStatus(401);

        $json = json_encode(['email' => 'ana@ejemplo.test', 'imagen' => $png]);
        $headers = $this->signed('PUT', '/interno/subsistemas/avatar', $json, 'x', 'intruso') + ['CONTENT_TYPE' => 'application/json', 'Accept' => 'application/json'];
        $this->call('PUT', '/interno/subsistemas/avatar', [], [], [], $this->transformHeadersToServerVars($headers), $json)->assertNotFound();

        $this->fromTickets('PUT', ['email' => 'ana@ejemplo.test', 'imagen' => base64_encode('<svg onload=alert(1)>')])->assertStatus(422);
        $this->fromTickets('PUT', ['email' => 'nadie@ejemplo.test', 'imagen' => $png])->assertNotFound();
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_a_replayed_inbound_request_is_rejected(): void
    {
        $user = User::factory()->active()->create(['email' => 'ana@ejemplo.test']);
        $json = json_encode(['email' => 'ana@ejemplo.test', 'imagen' => base64_encode($this->png())]);
        $headers = $this->signed('PUT', '/interno/subsistemas/avatar', $json) + ['CONTENT_TYPE' => 'application/json', 'Accept' => 'application/json'];
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('PUT', '/interno/subsistemas/avatar', [], [], [], $server, $json)->assertOk();
        $this->call('PUT', '/interno/subsistemas/avatar', [], [], [], $server, $json)->assertStatus(401);
        $this->assertNotNull($user->refresh()->avatar_path);
    }

    public function test_import_inherits_tickets_photos_without_overwriting_existing_ones(): void
    {
        $withPhoto = User::factory()->active()->create(['email' => 'conf@ejemplo.test']);
        $without = User::factory()->active()->create(['email' => 'sin@ejemplo.test']);
        $empty = User::factory()->active()->create(['email' => 'vacia@ejemplo.test']);
        foreach ([$withPhoto, $without, $empty] as $u) {
            $this->withTicketsAccess($u);
        }
        app(AvatarService::class)->setFromBytes($withPhoto, $this->png(10 + 30, 40), propagate: false);
        $kept = $withPhoto->refresh()->avatar_path;

        Http::fake([
            'https://tickets.test/interno/portal/avatar*' => fn (Request $r) => str_contains($r->url(), 'sin%40')
                ? Http::response(['imagen' => base64_encode($this->png())])
                : Http::response(['imagen' => null]),
        ]);

        $this->artisan('portal:importar-fotos', ['subsistema' => 'tickets'])->assertSuccessful();

        $this->assertNotNull($without->refresh()->avatar_path);
        $this->assertNull($empty->refresh()->avatar_path);
        $this->assertSame($kept, $withPhoto->refresh()->avatar_path);
    }
}
