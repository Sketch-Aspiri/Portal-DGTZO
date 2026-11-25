<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PortalRole;
use App\Services\Tutoriales\CatalogoGuias;
use App\Support\Subsistemas;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Cuida las guías reales: que el catálogo, los textos y las capturas coincidan. */
class TutorialesCoherenciaTest extends TestCase
{
    public function test_every_guide_has_valid_data_and_its_text(): void
    {
        $guias = app(CatalogoGuias::class)->todas();
        $this->assertNotEmpty($guias);

        foreach ($guias as $slug => $guia) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]{1,80}$/', $slug);
            $this->assertArrayHasKey($guia->sistema, config('guias.sistemas'), $slug);
            $this->assertFileExists($guia->archivo(), $slug);
            $this->assertTrue($guia->actualizada->gte($guia->publicada), "{$slug}: actualizada antes de publicada");

            $validos = $guia->sistema === 'portal'
                ? array_column(PortalRole::cases(), 'value')
                : Subsistemas::rolesDe($guia->sistema);
            $this->assertSame([], array_values(array_diff($guia->roles ?? [], $validos)), "{$slug}: rol desconocido");
        }
    }

    public function test_cited_images_exist_and_none_is_left_over(): void
    {
        foreach (app(CatalogoGuias::class)->todas() as $slug => $guia) {
            preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', File::get($guia->archivo()), $m);

            foreach ($m[2] as $i => $archivo) {
                $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9-]*\.png$/', $archivo, $slug);
                $this->assertNotSame('', trim($m[1][$i]), "{$slug}: {$archivo} sin descripción");
                $this->assertFileExists($guia->carpetaImagenes().'/'.$archivo, $slug);
            }

            $existentes = is_dir($guia->carpetaImagenes())
                ? array_map(fn ($f) => $f->getFilename(), File::files($guia->carpetaImagenes()))
                : [];
            $this->assertSame([], array_values(array_diff($existentes, $m[2])), "{$slug}: capturas sin usar");
        }
    }

    public function test_there_are_no_texts_or_image_folders_outside_the_catalog(): void
    {
        $guias = app(CatalogoGuias::class)->todas();
        $textos = $guias->map(fn ($g) => "{$g->sistema}/{$g->slug}.md")->values()->all();
        $carpetas = $guias->map(fn ($g) => "{$g->sistema}/{$g->slug}")->values()->all();

        foreach (array_keys(config('guias.sistemas')) as $sistema) {
            foreach (File::glob(config('guias.ruta')."/{$sistema}/*.md") as $archivo) {
                $this->assertContains($sistema.'/'.basename($archivo), $textos);
            }
            foreach (File::glob(config('guias.ruta')."/img/{$sistema}/*", GLOB_ONLYDIR) as $carpeta) {
                $this->assertContains($sistema.'/'.basename($carpeta), $carpetas);
            }
        }
    }
}
