<?php

declare(strict_types=1);

/*
 * Calendario oficial del portal. Las capas de cumpleaños y vacaciones se leen del Dashboard por el puente firmado;
 * mientras el Dashboard no tenga los endpoints desplegados, se dejan apagadas.
 */
return [
    // Capas del Dashboard (cumpleaños y vacaciones). Apagadas por omisión: encender solo con el Dashboard ya actualizado.
    'capas_dashboard' => (bool) env('CALENDARIO_CAPAS_DASHBOARD', false),

    // Las vacaciones del personal las ve quien administra el portal; con `true` las ve toda cuenta activa.
    'vacaciones_para_todos' => (bool) env('CALENDARIO_VACACIONES_PARA_TODOS', false),

    // Segundos que se guarda cada consulta al Dashboard (por rango de fechas).
    'cache_capas' => 600,
];
