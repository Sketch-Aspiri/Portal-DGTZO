<?php

declare(strict_types=1);

namespace App\Services\Tutoriales;

use Illuminate\Support\Str;

/** Markdown de una guía a HTML, con su índice de pasos y las capturas servidas por la ruta protegida. */
final class RenderizadorGuia
{
    /**
     * @return array{html: string, indice: list<array{id: string, titulo: string}>}
     */
    public function renderizar(Guia $guia): array
    {
        $html = Str::markdown((string) file_get_contents($guia->archivo()), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Los encabezados de las tablas dicen que encabezan columnas.
        $html = (string) preg_replace('#<th(?=[\s>])#', '<th scope="col"', $html);

        $indice = [];
        $usados = [];

        // Cada «## Título» es un paso: ancla propia y entrada en el índice.
        $html = (string) preg_replace_callback('#<h2>(.*?)</h2>#s', function (array $m) use (&$indice, &$usados): string {
            $titulo = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5);
            $base = Str::slug($titulo) ?: 'paso';
            $usados[$base] = ($usados[$base] ?? 0) + 1;
            $id = $usados[$base] > 1 ? $base.'-'.$usados[$base] : $base;
            $indice[] = ['id' => $id, 'titulo' => $titulo];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, $html);

        // En el Markdown la captura se cita solo por su nombre de archivo: ![texto](01-pantalla.png)
        $html = (string) preg_replace_callback(
            '#<img src="([a-z0-9][a-z0-9-]*\.png)" alt="([^"]*)"\s*/?>#',
            function (array $m) use ($guia): string {
                $ruta = $guia->carpetaImagenes().DIRECTORY_SEPARATOR.$m[1];
                $existe = is_file($ruta);
                $url = e(route('tutorials.image', ['slug' => $guia->slug, 'captura' => basename($m[1], '.png'), 'v' => $existe ? filemtime($ruta) : 0]));
                [$ancho, $alto] = $existe ? (getimagesize($ruta) ?: [0, 0]) : [0, 0];
                $medidas = $ancho > 0 ? ' width="'.$ancho.'" height="'.$alto.'"' : '';
                $clase = $alto > $ancho ? 'captura captura-celular' : 'captura';

                // $m[2] ya viene escapado por el conversor de Markdown.
                return '<button type="button" class="'.$clase.'" data-captura="'.$url.'" aria-label="'.e(__('portal.tutorials.zoom')).': '.$m[2].'">'
                    .'<img src="'.$url.'" alt="'.$m[2].'" loading="lazy"'.$medidas.'></button>';
            },
            $html
        );

        return ['html' => $html, 'indice' => $indice];
    }
}
