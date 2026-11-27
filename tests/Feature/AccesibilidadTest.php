<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accesibilidad: las preferencias se aplican antes de pintar y el panel está en todas las pantallas públicas.
 * El comportamiento en navegador se prueba con tools/capturas/panel.mjs.
 */
class AccesibilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_pantallas_publicas_ofrecen_el_panel_y_aplican_la_preferencia_al_cargar(): void
    {
        foreach ([route('home'), route('login'), route('register'), route('tutorials.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('js/accesibilidad-inicial.js', false)
                ->assertSee('x-data="panelAccesibilidad"', false)
                ->assertSee('Tamaño de letra');
        }
    }

    public function test_el_script_inicial_solo_lee_la_preferencia(): void
    {
        $codigo = (string) file_get_contents(public_path('js/accesibilidad-inicial.js'));

        $this->assertDoesNotMatchRegularExpression('/\b(eval|Function\(|innerHTML|document\.write)/', $codigo);
    }
}
