<?php

declare(strict_types=1);

/*
 * El portal es el centro del Sistema de Gestión Territorial Zona Oriente Tren Maya: emite el inicio de sesión
 * único para el Dashboard y Tickets con los mismos códigos firmados que ya se usan entre ellos.
 */
return [

    // Este sistema dentro del ecosistema.
    'sistema' => 'portal',

    // Dirección pública de cada sistema (sin diagonal final).
    'urls' => [
        'dashboard' => rtrim((string) env('ECOSISTEMA_DASHBOARD_URL', 'https://dashboard.zonaorientetm.tech'), '/'),
        'tickets' => rtrim((string) env('ECOSISTEMA_TICKETS_URL', 'https://tickets.zonaorientetm.tech'), '/'),
        'portal' => rtrim((string) env('ECOSISTEMA_PORTAL_URL', 'https://zonaorientetm.tech'), '/'),
    ],

    // ECOSISTEMA_SSO_SECRET: el MISMO valor en los .env de los tres sistemas (32+ caracteres aleatorios).
    // Sin él, cada sistema pide su propio login.
    'sso' => [
        'secreto' => env('ECOSISTEMA_SSO_SECRET'),
        // Vigencia de cada código firmado, en segundos.
        'vigencia' => 60,
    ],

    // Puente servidor a servidor con cada subsistema (cuentas aprobadas y catálogo de equipos o estaciones): llamadas
    // firmadas con HMAC, nunca desde el navegador. Un secreto por subsistema (32+ caracteres), el MISMO valor que
    // ECOSISTEMA_PUENTE_SECRETO_PORTAL en el .env de ese subsistema. Sin secreto, ese subsistema no se puede
    // aprovisionar desde el portal.
    'puente' => [
        'secretos' => [
            'dashboard' => env('PORTAL_PUENTE_SECRETO_DASHBOARD'),
            'tickets' => env('PORTAL_PUENTE_SECRETO_TICKETS'),
        ],
        'timeout' => 8,
        // Segundos que se guarda el catálogo (equipos, estaciones) que muestra el formulario de aprobación.
        'cache_catalogo' => 60,
    ],
    // Foto de perfil única: el portal guarda la foto y la reparte a los subsistemas que ya la reciben (`subsistemas`);
    // un cambio hecho en un subsistema llega por /interno/subsistemas/avatar con el secreto de ese subsistema.
    'avatares' => [
        'subsistemas' => array_values(array_filter(array_map('trim', explode(',', (string) env('PORTAL_AVATAR_SUBSISTEMAS', 'tickets'))))),
        'directorio' => 'avatars',
        'tamano' => 256,
        'max_kb' => (int) env('AVATAR_MAX_KB', 4096),
        'max_dimension' => 6000,
        'max_bytes_puente' => 1024 * 1024,
    ],
];
