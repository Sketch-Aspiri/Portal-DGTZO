<?php

/*
 * Una sección con tabla para probar la hoja de cálculo (hoja.mjs). Se ejecuta después de semillas/dashboard.php, sobre
 * la misma base desechable. Imprime el identificador (slug) de la sección.
 */

use App\Enums\TipoColumna;
use App\Models\Seccion;
use App\Models\SeccionFila;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

$raiz = $argv[1];
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($base, '_capturas') || ! app()->environment('local')) {
    fwrite(STDERR, "Base no permitida: {$base}\n");
    exit(1);
}

$seccion = Seccion::factory()->create(['nombre' => 'Inventario de radios', 'slug' => 'inventario-de-radios']);
$tabla = $seccion->tablas()->create(['nombre' => 'Radios', 'orden' => 1]);

$orden = 0;
foreach (['Equipo' => TipoColumna::Texto, 'Cantidad' => TipoColumna::Numero, 'Observaciones' => TipoColumna::Texto] as $nombre => $tipo) {
    $tabla->columnas()->create([
        'seccion_id' => $seccion->id, 'nombre' => $nombre, 'clave' => Str::slug($nombre, '_'), 'tipo' => $tipo, 'orden' => ++$orden,
    ]);
}

foreach ([['Radio portátil', 5, 'Completo'], ['Batería', 12, ''], ['Cargador', 3, 'Revisar uno']] as [$equipo, $cantidad, $nota]) {
    (new SeccionFila(['datos' => ['equipo' => $equipo, 'cantidad' => $cantidad, 'observaciones' => $nota]]))
        ->forceFill(['seccion_id' => $seccion->id, 'seccion_tabla_id' => $tabla->id])->save();
}

echo $seccion->slug;
