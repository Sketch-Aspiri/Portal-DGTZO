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

    'guias' => [
        'registro' => [
            'sistema' => 'portal', 'titulo' => 'Crear tu cuenta y pedir acceso',
            'resumen' => 'Regístrate, indica qué sistemas necesitas y sigue tu solicitud.',
            'publica' => true, 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'iniciar-sesion' => [
            'sistema' => 'portal', 'titulo' => 'Iniciar sesión y recuperar la contraseña',
            'resumen' => 'Entra al portal, abre tus sistemas y recupera tu acceso si olvidaste la contraseña.',
            'publica' => true, 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'verificacion-dos-pasos' => [
            'sistema' => 'portal', 'titulo' => 'Verificación en dos pasos',
            'resumen' => 'Actívala con tu celular y guarda los códigos de recuperación.',
            'publica' => true, 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'app-y-notificaciones' => [
            'sistema' => 'portal', 'titulo' => 'La app y las notificaciones',
            'resumen' => 'Instala el portal en tu celular y recibe los avisos de tus sistemas.',
            'publica' => true, 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'accesibilidad' => [
            'sistema' => 'portal', 'titulo' => 'Accesibilidad',
            'resumen' => 'Letra grande, contraste, uso con teclado, la hoja de cálculo y cómo reportar un problema.',
            'publica' => true, 'publicada' => '2026-11-27',
        ],
        'perfil' => [
            'sistema' => 'portal', 'titulo' => 'Tu perfil',
            'resumen' => 'Foto, nombre, contraseña y apariencia: un solo perfil para todos los sistemas.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'calendario' => [
            'sistema' => 'portal', 'titulo' => 'Calendario oficial',
            'resumen' => 'Consulta eventos, días inhábiles y fechas límite.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'calendario-administrar' => [
            'sistema' => 'portal', 'titulo' => 'Publicar en el calendario',
            'resumen' => 'Crea, corrige y elimina entradas del calendario oficial.',
            'roles' => ['administrador', 'jefe_zona'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'cuentas' => [
            'sistema' => 'portal', 'titulo' => 'Administrar cuentas y accesos',
            'resumen' => 'Aprueba solicitudes, crea cuentas, cambia roles y da de baja.',
            'roles' => ['administrador', 'jefe_zona'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-primeros-pasos' => [
            'sistema' => 'tickets', 'titulo' => 'Tickets: primeros pasos',
            'resumen' => 'Qué es un ticket y una actividad, el menú, tus pendientes, avisos y perfil.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-solicitudes' => [
            'sistema' => 'tickets', 'titulo' => 'Crear y tomar tickets',
            'resumen' => 'Levanta una solicitud, envíala al equipo correcto y toma tickets de la bolsa.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-avance' => [
            'sistema' => 'tickets', 'titulo' => 'Avanzar, revisar y comentar',
            'resumen' => 'El flujo de estados, comentarios, archivos adjuntos y aprobación del trabajo.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-asignar' => [
            'sistema' => 'tickets', 'titulo' => 'Asignar y dar seguimiento al equipo',
            'resumen' => 'Asigna responsables, revisa lo de tu equipo y exporta a Excel.',
            'roles' => ['administrador', 'jefe_zona', 'coordinador'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-actividades' => [
            'sistema' => 'tickets', 'titulo' => 'Actividades y tareas recurrentes',
            'resumen' => 'Planifica actividades, divide en subtareas y programa las que se repiten.',
            'roles' => ['administrador', 'jefe_zona', 'coordinador'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-correos' => [
            'sistema' => 'tickets', 'titulo' => 'Correos entrantes',
            'resumen' => 'Revisa las solicitudes que llegan por correo y conviértelas en actividades.',
            'roles' => ['administrador', 'jefe_zona', 'coordinador'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-seguimiento' => [
            'sistema' => 'tickets', 'titulo' => 'Panel de seguimiento',
            'resumen' => 'Indicadores, gráficas y carga de trabajo por persona.',
            'roles' => ['administrador', 'jefe_zona', 'coordinador'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-chat' => [
            'sistema' => 'tickets', 'titulo' => 'Chat',
            'resumen' => 'Mensajes directos y el canal de tu equipo.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-administracion' => [
            'sistema' => 'tickets', 'titulo' => 'Administrar usuarios, equipos y categorías',
            'resumen' => 'Cuentas, roles, equipos, categorías y la bitácora.',
            'roles' => ['administrador', 'jefe_zona'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'tickets-estructura' => [
            'sistema' => 'tickets', 'titulo' => 'Estaciones, coordinaciones y buzones',
            'resumen' => 'Estructura de la organización y buzón de correo de cada coordinación.',
            'roles' => ['administrador'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-inicio' => [
            'sistema' => 'dashboard', 'titulo' => 'Dashboard: primeros pasos',
            'resumen' => 'Inicio, menú, perfil y uso en el celular.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-asistencia' => [
            'sistema' => 'dashboard', 'titulo' => 'Capturar la asistencia diaria',
            'resumen' => 'Registra el estatus del personal, los comisionados y genera el oficio.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-asistencia-zona' => [
            'sistema' => 'dashboard', 'titulo' => 'Asistencia de la Zona',
            'resumen' => 'Quién ya capturó, el detalle por estación y el oficio de la Zona.',
            'roles' => ['jefe_zona', 'administrador', 'edificio'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-horarios' => [
            'sistema' => 'dashboard', 'titulo' => 'Rol de horarios',
            'resumen' => 'Consulta el horario semanal, pide cambios y autorízalos.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-vacaciones' => [
            'sistema' => 'dashboard', 'titulo' => 'Vacaciones',
            'resumen' => 'Consulta el rol, elige tus periodos y revisa las selecciones.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-cumpleanos' => [
            'sistema' => 'dashboard', 'titulo' => 'Cumpleaños',
            'resumen' => 'Quién cumple hoy, en los próximos días y en cada mes.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-personal' => [
            'sistema' => 'dashboard', 'titulo' => 'Personal',
            'resumen' => 'Consulta, registra y actualiza la plantilla.',
            'roles' => ['jefe_zona', 'administrador', 'edificio'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-insumos' => [
            'sistema' => 'dashboard', 'titulo' => 'Solicitud de insumos',
            'resumen' => 'Captura la solicitud mensual y síguela hasta su aprobación.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-insumos-zona' => [
            'sistema' => 'dashboard', 'titulo' => 'Catálogo, cotizaciones y facturas',
            'resumen' => 'Administra el catálogo, proveedores, inventario, cotizaciones y comprobación.',
            'roles' => ['jefe_zona', 'administrador', 'edificio'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-papeleria' => [
            'sistema' => 'dashboard', 'titulo' => 'Solicitud de papelería',
            'resumen' => 'Pide los artículos del mes y revisa las solicitudes.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-agua' => [
            'sistema' => 'dashboard', 'titulo' => 'Registro de agua',
            'resumen' => 'Registra las compras de garrafones del mes y envíalas a la Zona.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-afluencia' => [
            'sistema' => 'dashboard', 'titulo' => 'Flujo de pasajeros',
            'resumen' => 'Captura diaria de abordajes y boletos por estación.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-ptar' => [
            'sistema' => 'dashboard', 'titulo' => 'Reporte de estatus PTAR',
            'resumen' => 'Reporte mensual de la planta de tratamiento y sus hallazgos.',
            'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
        'dashboard-administracion' => [
            'sistema' => 'dashboard', 'titulo' => 'Administrar cuentas, equipos y menú',
            'resumen' => 'Cuentas del Dashboard, equipos por estación y secciones del menú.',
            'roles' => ['jefe_zona', 'administrador'], 'publicada' => '2026-11-25', 'actualizada' => '2026-11-27',
        ],
    ],
];
