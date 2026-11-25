<?php

/*
 * Datos ficticios para las capturas del portal. Lo ejecuta portal.mjs sobre una base desechable.
 */

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Models\CalendarioEvento;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\SubsystemNotice;
use Illuminate\Contracts\Console\Kernel;

$raiz = dirname(__DIR__, 3);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Candado: nunca sobre una base que no sea de capturas.
$base = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($base, '_capturas.sqlite') || ! app()->environment('local')) {
    fwrite(STDERR, "Base no permitida para las capturas: {$base}\n");
    exit(1);
}

$clave = 'Clave-Segura-2026!';
$secreto = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

$crear = function (string $nombre, string $correo, UserStatus $estado = UserStatus::Active, PortalRole $rol = PortalRole::Usuario, bool $dosPasos = false) use ($clave, $secreto): User {
    $user = new User(['name' => $nombre, 'email' => $correo, 'password' => $clave]);
    $user->forceFill([
        'status' => $estado,
        'portal_role' => $rol,
        'decided_at' => $estado === UserStatus::Pending ? null : now(),
        'two_factor_secret' => $dosPasos ? $secreto : null,
        'two_factor_confirmed_at' => $dosPasos ? now() : null,
        'two_factor_recovery_codes' => $dosPasos ? [] : null,
    ])->save();

    return $user;
};

$acceso = function (User $user, string $sistema, string $rol): void {
    $a = $user->todosLosAccesos()->create(['subsistema' => $sistema, 'rol' => $rol]);
    $a->forceFill(['aprovisionamiento' => 'ok', 'aprovisionado_at' => now()])->save();
};

$admin = $crear('Alejandra Ruiz', 'alejandra.ruiz@ejemplo.mx', rol: PortalRole::Administrador, dosPasos: true);
$crear('Marco Peña', 'marco.pena@ejemplo.mx', rol: PortalRole::JefeZona);

$laura = $crear('Laura Gómez', 'laura.gomez@ejemplo.mx');
$acceso($laura, 'dashboard', 'estacion');
$acceso($laura, 'tickets', 'empleado');

$diego = $crear('Diego Hernández', 'diego.hernandez@ejemplo.mx');
$acceso($diego, 'tickets', 'coordinador');

// Cuenta aprobada que todavía no activa la verificación en dos pasos (la usa esa guía).
$crear('Sofía Navarro', 'sofia.navarro@ejemplo.mx', rol: PortalRole::JefeZona);

$crear('Raúl Méndez', 'raul.mendez@ejemplo.mx', UserStatus::Inactive);

// Solicitudes pendientes: una ya llena y otra sin llenar.
$pendiente = $crear('Carlos Ortega', 'carlos.ortega@ejemplo.mx', UserStatus::Pending);
(new SolicitudAcceso)->forceFill([
    'user_id' => $pendiente->id,
    'puesto' => 'Auxiliar de estación',
    'ubicacion' => 'Estación Tulum',
    'telefono' => '984 000 0000',
    'funciones' => 'Capturo la asistencia diaria de la estación y doy seguimiento a los insumos.',
    'accesos' => ['dashboard' => ['rol' => 'estacion', 'estacion_id' => 2]],
    'estado' => 'enviada',
])->save();
$crear('Patricia León', 'patricia.leon@ejemplo.mx', UserStatus::Pending);

$dia = fn (int $dias, string $hora = '15:00') => now()->addDays($dias)->setTimeFromTimeString($hora);
foreach ([
    ['evento', 'Reunión de coordinación de la Zona', 1, '15:00', false, 'Edificio Zonal'],
    ['fecha_limite', 'Entrega del rol de vacaciones', 3, '05:00', true, null],
    ['evento', 'Simulacro de evacuación', 6, '16:00', false, 'Estación Tulum'],
    ['inhabil', 'Día inhábil (ejemplo)', 9, '05:00', true, null],
    ['fecha_limite', 'Cierre de comprobaciones del mes', 12, '05:00', true, null],
    ['evento', 'Capacitación de primeros auxilios', -4, '14:00', false, 'Estación Bacalar'],
] as [$tipo, $titulo, $dias, $hora, $todo, $lugar]) {
    CalendarioEvento::create([
        'tipo' => $tipo, 'titulo' => $titulo, 'lugar' => $lugar,
        'inicia_at' => $dia($dias, $hora), 'todo_el_dia' => $todo,
    ]);
}

foreach ([$laura, $admin] as $persona) {
    foreach ([
        ['Nuevo ticket asignado', 'Falla en el torniquete 3', 'tickets'],
        ['Actividad por vencer', 'Reporte semanal de limpieza', 'tickets'],
        ['Asistencia pendiente', 'Falta capturar la asistencia de hoy', 'dashboard'],
    ] as [$titulo, $cuerpo, $origen]) {
        $persona->notifyNow(new SubsystemNotice($titulo, $cuerpo, 'https://zonaorientetm.tech', $origen), ['database']);
    }
}

echo "Datos de capturas listos\n";
