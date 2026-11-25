<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla de carga: la portada, las guías públicas y el acceso la incluyen.
 */
class PantallaCargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_pantallas_publicas_incluyen_la_pantalla_de_carga(): void
    {
        foreach ([route('home'), route('login'), route('tutorials.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="app-page-loader"', false)
                ->assertSee('data-cerrar-carga', false)
                ->assertSee('logo.png', false);
        }
    }
}
