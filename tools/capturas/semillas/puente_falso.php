<?php

/*
 * Puente de mentira para las capturas: contesta con un catálogo y capas ficticias a las llamadas que el portal
 * hace a los subsistemas.
 */

header('Content-Type: application/json');

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_ends_with($ruta, '/interno/portal/catalogo')) {
    $estaciones = ['Cancún Aeropuerto', 'Tulum', 'Felipe Carrillo Puerto', 'Bacalar', 'Chetumal Aeropuerto'];
    echo json_encode([
        'equipos' => [
            ['id' => 1, 'nombre' => 'Recursos Humanos', 'estacion' => 'Edificio Zonal', 'coordinacion' => 'Administración'],
            ['id' => 2, 'nombre' => 'Recursos Materiales', 'estacion' => 'Edificio Zonal', 'coordinacion' => 'Administración'],
            ['id' => 3, 'nombre' => 'Mantenimiento', 'estacion' => 'Tulum', 'coordinacion' => 'Operación'],
        ],
        'coordinaciones' => [
            ['id' => 1, 'nombre' => 'Administración', 'estacion' => 'Edificio Zonal'],
            ['id' => 2, 'nombre' => 'Operación', 'estacion' => 'Tulum'],
        ],
        'estaciones' => array_map(fn ($i, $n) => ['id' => $i + 1, 'nombre' => $n], array_keys($estaciones), $estaciones),
        'areas' => [['clave' => 'rh', 'nombre' => 'Recursos Humanos'], ['clave' => 'rm', 'nombre' => 'Recursos Materiales']],
    ]);

    return;
}

http_response_code(404);
echo '{}';
