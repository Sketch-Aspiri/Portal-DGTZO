<?php

namespace Tests\Feature;

use App\Enums\PortalRole;
use App\Models\CalendarioEvento;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\SubsystemNotice;
use App\Services\Tutoriales\CatalogoGuias;
use App\Services\TwoFactorService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesSubsystems;
use Tests\TestCase;

/**
 * Accesibilidad: cada pantalla del portal, por tipo de cuenta, cumple las reglas que se pueden comprobar en el HTML
 * (idioma, título, un h1, nombres de controles, referencias, tablas, imágenes), y las preferencias se aplican antes de
 * pintar. El comportamiento en navegador se prueba con tools/capturas (panel.mjs, base.mjs, portal-teclado.mjs).
 */
class AccesibilidadTest extends TestCase
{
    use FakesSubsystems;
    use RefreshDatabase;

    /** Rutas GET que no devuelven una pantalla: redirecciones, archivos y respuestas sin contenido. */
    private const SIN_PANTALLA = [
        'ecosistema.sso', 'ecosistema.salir', 'sesion.viva', 'avatars.show', 'tutorials.image', 'notifications.open',
        'storage.local', 'storage.local.upload',
    ];

    /** @var list<string> */
    private array $revisadas = [];

    public function test_las_pantallas_publicas_ofrecen_el_panel_y_aplican_la_preferencia_al_cargar(): void
    {
        foreach ([route('home'), route('login'), route('register'), route('tutorials.index'), route('accessibility')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('js/accesibilidad-inicial.js', false)
                ->assertSee('x-data="panelAccesibilidad"', false)
                ->assertSee('Tamaño de letra')
                ->assertSee(route('accessibility'), false);
        }
    }

    public function test_la_sesion_solo_continua_con_el_token_de_la_pagina(): void
    {
        $this->get(route('sesion.viva'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->withSession(['_token' => 'vigente'])->get(route('sesion.viva'), ['X-CSRF-TOKEN' => 'vigente'])->assertNoContent();
        $this->withSession(['_token' => 'nuevo'])->get(route('sesion.viva'), ['X-CSRF-TOKEN' => 'vigente'])->assertStatus(409);
    }

    public function test_el_script_inicial_solo_lee_la_preferencia(): void
    {
        $codigo = (string) file_get_contents(public_path('js/accesibilidad-inicial.js'));

        $this->assertDoesNotMatchRegularExpression('/\b(eval|Function\(|innerHTML|document\.write)/', $codigo);
    }

    public function test_la_declaracion_es_publica_y_dice_el_estado_real(): void
    {
        $this->get(route('accessibility'))->assertOk()
            ->assertSee('WCAG')->assertSee('parcialmente conforme')
            ->assertSee('No se han hecho pruebas con lectores de pantalla')
            ->assertDontSee('mailto:', false);

        $this->entrar(User::factory()->active()->create())->get(route('accessibility'))->assertOk()
            ->assertSee('parcialmente conforme');
        $this->actingAs(User::factory()->create())->get(route('accessibility'))->assertOk();
    }

    public function test_las_pantallas_de_cada_tipo_de_cuenta_cumplen_las_reglas_del_html(): void
    {
        $this->fakeSubsystems();
        config(['calendario.capas_dashboard' => false]);

        // Datos que hacen aparecer las partes opcionales de cada pantalla.
        $admin = User::factory()->administrador()->create();
        $usuaria = User::factory()->active()->create();
        foreach (['dashboard' => 'estacion', 'tickets' => 'empleado'] as $sistema => $rol) {
            $usuaria->todosLosAccesos()->create(['subsistema' => $sistema, 'rol' => $rol])
                ->forceFill(['aprovisionamiento' => $sistema === 'tickets' ? 'error' : 'ok'])->save();
        }
        $parcial = User::factory()->active()->create();
        $parcial->todosLosAccesos()->create(['subsistema' => 'dashboard', 'rol' => 'estacion']);
        $baja = User::factory()->inactive()->create();
        $sinSolicitud = User::factory()->create();
        $conSolicitud = User::factory()->create();
        (new SolicitudAcceso)->forceFill([
            'user_id' => $conSolicitud->id, 'puesto' => 'Auxiliar', 'ubicacion' => 'Tulum', 'funciones' => 'Capturo la asistencia diaria.',
            'accesos' => ['dashboard' => ['rol' => 'estacion', 'estacion_id' => 5, 'area' => 'rh']], 'estado' => 'enviada',
        ])->save();

        foreach ([$usuaria, $admin] as $persona) {
            $persona->notifyNow(new SubsystemNotice('Aviso de prueba', 'Cuerpo', 'https://tickets.test/x', 'tickets'), ['database']);
        }

        $hoy = now(config('app.display_timezone'))->startOfDay()->utc();
        foreach (range(1, 5) as $n) {
            CalendarioEvento::create(['tipo' => 'evento', 'titulo' => "Entrada {$n}", 'inicia_at' => $hoy->addHours(10), 'todo_el_dia' => false]);
        }
        $evento = CalendarioEvento::firstOrFail();

        // Visitante.
        $publicas = [route('home'), route('login'), route('register'), route('password.request'),
            route('password.reset', ['token' => 'abc', 'email' => 'a@ejemplo.mx']), route('tutorials.index'), route('accessibility')];
        foreach (app(CatalogoGuias::class)->todas()->filter(fn ($g) => $g->publica) as $guia) {
            $publicas[] = route('tutorials.show', $guia->slug);
        }
        $this->revisarTodas('visitante', $publicas);

        // Pendiente y de baja.
        $this->actingAs($sinSolicitud);
        $this->revisarTodas('pendiente', [route('account.status'), route('tutorials.index'), route('accessibility')]);
        $this->actingAs($conSolicitud);
        $this->revisarTodas('pendiente con solicitud', [route('account.status'), route('account.status', ['editar' => 1])]);
        $this->actingAs($baja);
        $this->revisarTodas('de baja', [route('account.status')]);

        // Usuaria con sus sistemas.
        $comunes = [route('dashboard'), route('notifications.index'), route('calendar.index'), route('profile.edit'),
            route('tutorials.index'), route('accessibility')];
        $this->entrar($usuaria);
        $this->revisarTodas('usuaria', $comunes);

        // Administradora: además, cuentas, calendario y todas las guías.
        $this->entrar($admin);
        $propias = [route('calendar.events.create'), route('calendar.events.edit', $evento), route('users.create'),
            route('users.show', $usuaria), route('users.show', $parcial), route('users.show', $baja),
            route('users.show', $conSolicitud), route('users.show', $sinSolicitud), route('users.show', $admin),
            route('users.index', ['q' => 'nadie'])];
        foreach (['pending', 'active', 'inactive'] as $estado) {
            $propias[] = route('users.index', ['estado' => $estado]);
        }
        foreach (app(CatalogoGuias::class)->todas() as $guia) {
            $propias[] = route('tutorials.show', $guia->slug);
        }
        $this->revisarTodas('administradora', [...$comunes, ...$propias]);

        // Verificación en dos pasos: activarla, guardar los códigos y el desafío.
        $nueva = User::factory()->active()->create(['portal_role' => PortalRole::JefeZona]);
        $this->actingAs($nueva);
        $this->revisarTodas('sin dos pasos', [route('two-factor.setup')]);
        $this->actingAs($admin)->withSession([TwoFactorService::SESSION_KEY => null]);
        $this->revisarTodas('desafío', [route('two-factor.challenge')]);
        $this->entrar($admin)->withSession(['recovery_codes' => ['AAAA-BBBB', 'CCCC-DDDD']]);
        $this->revisarTodas('códigos', [route('two-factor.recovery')]);

        // Ninguna pantalla nueva queda fuera de la revisión.
        $faltan = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && in_array('web', $r->gatherMiddleware(), true))
            ->map(fn ($r) => $r->getName())->filter()
            ->reject(fn (string $n) => in_array($n, self::SIN_PANTALLA, true) || in_array($n, $this->revisadas, true))
            ->values()->all();
        $this->assertSame([], $faltan, 'Rutas GET sin revisar: agrégalas aquí o a SIN_PANTALLA.');
    }

    public function test_el_calendario_es_una_tabla_con_dias_completos_y_no_depende_del_color(): void
    {
        $this->travelTo('2026-12-10 18:00:00');
        $usuaria = User::factory()->active()->create();
        foreach (range(1, 5) as $n) {
            CalendarioEvento::create(['tipo' => 'fecha_limite', 'titulo' => "Entrega {$n}", 'inicia_at' => '2026-12-10 15:00:00', 'todo_el_dia' => false]);
        }

        $x = $this->documento($this->entrar($usuaria)->get(route('calendar.index', ['tipo' => 'fecha_limite']))->assertOk());

        $this->assertSame(7, $x->query('//table/thead//th[@scope="col"]')->length);
        $this->assertSame(1, $x->query('//table/caption')->length);
        $this->assertStringContainsString('Jueves 10 de diciembre de 2026', $x->query('//td//*[@aria-current="date"]')->item(0)->textContent);
        $this->assertStringContainsString('Hoy', $x->query('//td//*[@aria-current="date"]')->item(0)->textContent);
        // Las cinco entradas están en la cuadrícula (tres a la vista y dos en «+2 más»), cada una con su tipo en texto.
        $this->assertSame(5, $x->query('//td//li[.//span[@class="sr-only" and contains(., "Fecha límite")]]')->length);
        $this->assertSame(2, $x->query('//td//details//li')->length);
        $this->assertSame(0, $x->query('//a[@aria-pressed]')->length);
        $this->assertSame(1, $x->query('//a[@aria-current="true"]')->length);
    }

    public function test_los_encabezados_de_las_tablas_de_las_guias_tienen_alcance(): void
    {
        $ruta = storage_path('framework/testing/guias-a11y-'.uniqid());
        mkdir($ruta.'/portal', 0777, true);
        file_put_contents($ruta.'/portal/tabla.md', "## Paso\n\n| Uno | Dos |\n|:--|--:|\n| a | b |\n");
        config(['guias.ruta' => $ruta, 'guias.guias' => ['tabla' => [
            'sistema' => 'portal', 'titulo' => 'Tabla', 'resumen' => 'R', 'publica' => true, 'publicada' => '2026-01-01',
        ]]]);

        $x = $this->documento($this->get(route('tutorials.show', 'tabla'))->assertOk());

        $this->assertSame(2, $x->query('//th[@scope="col"]')->length);
        unlink($ruta.'/portal/tabla.md');
        rmdir($ruta.'/portal');
        rmdir($ruta);
    }

    private function entrar(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorService::SESSION_KEY => $user->getKey()]);
    }

    /** @param  list<string>  $urls */
    private function revisarTodas(string $quien, array $urls): void
    {
        $problemas = [];

        foreach ($urls as $url) {
            $respuesta = $this->get($url);
            $this->assertSame(200, $respuesta->status(), "{$quien}: {$url}");
            $this->revisadas[] = Route::getRoutes()->match(request()->create($url))->getName();

            foreach ($this->problemas($this->documento($respuesta)) as $problema) {
                $problemas[] = "{$quien} ".(parse_url($url, PHP_URL_PATH) ?: '/').": {$problema}";
            }
        }

        $this->assertSame([], $problemas, implode("\n", $problemas));
    }

    private function documento(TestResponse $respuesta): DOMXPath
    {
        $previo = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8">'.$respuesta->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return new DOMXPath($dom);
    }

    /** @return list<string> */
    private function problemas(DOMXPath $x): array
    {
        $malos = [];
        $uno = fn (string $consulta) => $x->query($consulta)->length;
        // Lo que está dentro de <template> todavía no existe en la página.
        $vivos = fn (string $consulta) => iterator_to_array($x->query($consulta.'[not(ancestor::template)]'));
        $ids = [];

        if ($x->query('/html')->item(0)?->getAttribute('lang') !== 'es') {
            $malos[] = 'sin lang="es"';
        }
        if (trim((string) $x->query('//head/title')->item(0)?->textContent) === '') {
            $malos[] = 'sin <title>';
        }
        if (count($vivos('//h1')) !== 1) {
            $malos[] = 'h1: '.count($vivos('//h1'));
        }

        foreach ($vivos('//*[@id]') as $el) {
            $id = $el->getAttribute('id');
            if (isset($ids[$id])) {
                $malos[] = "id repetido: {$id}";
            }
            $ids[$id] = true;
        }

        foreach (['aria-labelledby', 'aria-describedby', 'aria-controls', 'for'] as $atributo) {
            foreach ($vivos("//*[@{$atributo}]") as $el) {
                foreach (preg_split('/\s+/', trim($el->getAttribute($atributo))) as $ref) {
                    if (! isset($ids[$ref])) {
                        $malos[] = "{$atributo} apunta a un id inexistente: {$ref}";
                    }
                }
            }
        }

        if ($uno('//th[not(@scope)]') > 0) {
            $malos[] = 'th sin scope';
        }
        if ($uno('//img[not(@alt)]') > 0) {
            $malos[] = 'img sin alt';
        }

        $controles = '//*[self::select or self::textarea or (self::input and not(@type="hidden" or @type="submit" or @type="button" or @type="reset" or @type="image"))]';
        foreach ($x->query($controles) as $control) {
            if (! $this->tieneEtiqueta($x, $control)) {
                $malos[] = 'control sin nombre: '.$control->getAttribute('name').' '.$control->getAttribute('id');
            }
        }

        foreach ($x->query('//button | //a[@href] | //summary') as $el) {
            if (! $this->tieneNombre($el)) {
                $malos[] = $el->nodeName.' sin nombre: '.($el->getAttribute('href') ?: $el->getAttribute('class'));
            }
        }

        return $malos;
    }

    private function tieneEtiqueta(DOMXPath $x, DOMElement $control): bool
    {
        foreach (['aria-label', ':aria-label', 'aria-labelledby', ':aria-labelledby', 'title'] as $atributo) {
            if (trim($control->getAttribute($atributo)) !== '') {
                return true;
            }
        }

        $etiquetas = iterator_to_array($x->query('ancestor::label', $control));
        if (($id = $control->getAttribute('id')) !== '') {
            $etiquetas = [...$etiquetas, ...iterator_to_array($x->query('//label[@for="'.$id.'"]'))];
        }

        foreach ($etiquetas as $etiqueta) {
            if ($this->tieneNombre($etiqueta)) {
                return true;
            }
        }

        return false;
    }

    /** Texto propio (sin lo oculto al lector), texto que pondrá Alpine, o un atributo que dé nombre. */
    private function tieneNombre(DOMElement $el): bool
    {
        foreach (['aria-label', ':aria-label', 'aria-labelledby', 'title', ':title', 'x-text', 'data-pausar'] as $atributo) {
            if (trim($el->getAttribute($atributo)) !== '') {
                return true;
            }
        }

        foreach ($el->childNodes as $hijo) {
            if ($hijo instanceof DOMElement) {
                if ($hijo->getAttribute('aria-hidden') === 'true' || in_array($hijo->nodeName, ['select', 'textarea', 'input'], true)) {
                    continue;
                }
                if (($hijo->nodeName === 'img' && trim($hijo->getAttribute('alt')) !== '') || $this->tieneNombre($hijo)) {
                    return true;
                }
            } elseif ($hijo->nodeType === XML_TEXT_NODE && trim($hijo->textContent) !== '') {
                return true;
            }
        }

        return false;
    }
}
