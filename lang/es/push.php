<?php

return [
    'errors' => [
        'invalid_endpoint' => 'El servicio de notificaciones del navegador no es válido.',
        'too_many' => 'Ya tienes el máximo de dispositivos con notificaciones activadas. Desactívalas en otro dispositivo primero.',
    ],
    'systems' => [
        'dashboard' => 'Dashboard',
        'tickets' => 'Tickets',
        'portal' => 'Portal',
    ],
    'ui' => [
        'title' => 'Notificaciones en este dispositivo',
        'intro' => 'Recibe en tu celular o computadora los avisos de todos los sistemas, aunque el portal esté cerrado.',
        'enable' => 'Activar notificaciones',
        'disable' => 'Desactivar notificaciones',
        'state_on' => 'Activadas en este dispositivo.',
        'state_off' => 'Desactivadas en este dispositivo.',
        'state_blocked' => 'Bloqueadas por el navegador. Permítelas en la configuración del sitio para activarlas.',
        'state_unsupported' => 'Este navegador no admite notificaciones push.',
        'state_ios_install' => 'En iPhone, primero instala la app: Compartir → «Agregar a pantalla de inicio», y ábrela desde ahí.',
        'state_missing_key' => 'Las notificaciones push no están configuradas en el servidor.',
        'error' => 'No se pudo completar la acción. Inténtalo de nuevo.',
    ],
    'center' => [
        'title' => 'Notificaciones',
        'empty' => 'No tienes notificaciones.',
        'mark_all' => 'Marcar todas como leídas',
        'unread' => 'Sin leer',
        'preferences' => 'Avisos al celular',
        'preferences_intro' => 'Elige de qué sistemas quieres recibir avisos en tu celular. Los silenciados no suenan, pero sus avisos siguen apareciendo en esta lista.',
        'state_on' => 'Avisos activados',
        'state_muted' => 'Silenciado',
        'switch_label' => 'Recibir avisos de :system en el celular',
        'saving' => 'Guardando…',
        'saved_short' => 'Guardado',
        'save_error' => 'No se pudo guardar. Inténtalo de nuevo.',
        'save_button' => 'Guardar',
        'saved' => 'Preferencias guardadas.',
    ],
];
