<?php

/*
 * Catálogo de tutoriales (/tutoriales). El texto de cada guía vive en resources/guias/<sistema>/<slug>.md y sus
 * capturas en resources/guias/img/<sistema>/<slug>/. Cómo agregar o actualizar una: docs/tutoriales.md.
 *
 * - 'roles': sin él, cualquiera con acceso al sistema. En 'portal' son roles del portal (PortalRole); en los demás,
 *   claves de roles de config/subsistemas.php.
 * - 'publica': se ve sin iniciar sesión.
 * - 'publicada' / 'actualizada': fechas AAAA-MM-DD; ponen la etiqueta «Nuevo» o «Actualizado» durante 'dias_novedad'.
 * El orden de la lista es el orden en pantalla.
 */
return [
    'dias_novedad' => 30,

    // Carpeta con los textos y las capturas.
    'ruta' => resource_path('guias'),

    'sistemas' => [
        'portal' => 'Portal',
        'tickets' => 'Tickets',
        'dashboard' => 'Dashboard',
    ],

    'guias' => [],
];
