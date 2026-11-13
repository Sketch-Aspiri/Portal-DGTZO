<?php

/*
 * Catálogo de subsistemas del Sistema de Gestión Territorial Zona Oriente Tren Maya.
 * Agregar un subsistema nuevo = agregar una entrada aquí (clave, nombre, URL y roles).
 */
return [
    'dashboard' => [
        'nombre' => 'Dashboard',
        'descripcion' => 'Seguimiento de estaciones, insumos, asistencia y facturas de la Zona Oriente.',
        'url' => env('SUBSISTEMA_DASHBOARD_URL', 'https://dashboard.zonaorientetm.tech'),
        'roles' => [
            'jefe_zona' => 'Jefe de Zona',
            'administrador' => 'Administrador',
            'edificio' => 'Edificio',
            'estacion' => 'Estación',
        ],
        // Roles con alcance amplio: solo un administrador del portal puede otorgarlos.
        'roles_privilegiados' => ['jefe_zona', 'administrador', 'edificio'],
    ],
    'tickets' => [
        'nombre' => 'Tickets',
        'descripcion' => 'Solicitudes, actividades y seguimiento del trabajo de cada equipo.',
        'url' => env('SUBSISTEMA_TICKETS_URL', 'https://tickets.zonaorientetm.tech'),
        'roles' => [
            'administrador' => 'Administrador',
            'jefe_zona' => 'Jefe de zona',
            'coordinador' => 'Coordinador',
            'empleado' => 'Empleado',
        ],
        'roles_privilegiados' => ['administrador', 'jefe_zona'],
    ],
];
