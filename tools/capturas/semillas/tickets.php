<?php

/*
 * Datos ficticios para las capturas de Tickets. Se ejecuta sobre una base desechable, después de DemoDataSeeder.
 * Uso: php tickets.php <carpeta de Tickets>   Imprime en JSON los registros que usa el guion.
 */

use App\Models\Activity;
use App\Models\Category;
use App\Models\Coordination;
use App\Models\IncomingEmail;
use App\Models\Station;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ChatService;
use App\Services\CommentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$raiz = rtrim($argv[1] ?? '', '/\\');
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Candado: nunca sobre una base que no sea de capturas.
$base = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($base, '_capturas.sqlite') || ! app()->environment('local')) {
    fwrite(STDERR, "Base no permitida para las capturas: {$base}\n");
    exit(1);
}

$nombres = [
    'admin@demo.test' => ['Alejandra Ruiz', 'alejandra.ruiz'],
    'jefe@demo.test' => ['Marco Peña', 'marco.pena'],
    'jefe.norte@demo.test' => ['Sofía Navarro', 'sofia.navarro'],
    'coordinador.operaciones@demo.test' => ['Diego Hernández', 'diego.hernandez'],
    'empleado1.operaciones@demo.test' => ['Laura Gómez', 'laura.gomez'],
    'empleado2.operaciones@demo.test' => ['Iván Castillo', 'ivan.castillo'],
    'empleado3.operaciones@demo.test' => ['Mariana Torres', 'mariana.torres'],
    'coordinador.mantenimiento@demo.test' => ['Elena Vargas', 'elena.vargas'],
    'empleado1.mantenimiento@demo.test' => ['Carlos Ortega', 'carlos.ortega'],
    'empleado2.mantenimiento@demo.test' => ['Patricia León', 'patricia.leon'],
    'empleado3.mantenimiento@demo.test' => ['Raúl Méndez', 'raul.mendez'],
    'coordinador.administracion@demo.test' => ['Gabriela Soto', 'gabriela.soto'],
    'empleado1.administracion@demo.test' => ['Hugo Ramírez', 'hugo.ramirez'],
    'empleado2.administracion@demo.test' => ['Nadia Cruz', 'nadia.cruz'],
    'empleado3.administracion@demo.test' => ['Óscar Díaz', 'oscar.diaz'],
    'pendiente@demo.test' => ['Julia Campos', 'julia.campos'],
];

foreach ($nombres as $viejo => [$nombre, $alias]) {
    $user = User::query()->where('email', $viejo)->first();
    if ($user === null) {
        continue;
    }
    $user->forceFill(['name' => $nombre, 'email' => "{$alias}@ejemplo.mx"]);
    if ($user->requiresTwoFactor()) {
        $user->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
    }
    $user->save();
}

$renombrar = function (string $modelo, array $cambios): void {
    foreach ($cambios as $antes => $despues) {
        $modelo::query()->where('name', $antes)->update(['name' => $despues]);
    }
};
$renombrar(Station::class, ['Estación Demo' => 'Estación Tulum', 'Estación Norte Demo' => 'Edificio Zonal']);
$renombrar(Coordination::class, ['Coordinación Demo' => 'Operación', 'Coordinación Norte Demo' => 'Administración']);
$renombrar(Team::class, ['Administracion' => 'Administración']);

// Títulos sin el prefijo «[Demo]» ni el equipo entre paréntesis.
foreach ([Ticket::class, Activity::class] as $modelo) {
    foreach ($modelo::withTrashed()->get() as $item) {
        $item->forceFill([
            'title' => trim(preg_replace(['/^\[Demo\]\s*/', '/\s*\((Operaciones|Mantenimiento|Administracion)\)$/'], '', $item->title)),
            'description' => 'Trabajo de ejemplo para mostrar cómo funciona el sistema.',
        ])->saveQuietly();
    }
}

$persona = fn (string $alias): User => User::query()->where('email', "{$alias}@ejemplo.mx")->firstOrFail();
$laura = $persona('laura.gomez');
$diego = $persona('diego.hernandez');
$equipo = Team::query()->where('name', 'Operaciones')->firstOrFail();

$delEquipo = fn () => Ticket::query()->where('team_id', $equipo->id);
$propios = fn () => $delEquipo()->whereHas('assignments', fn ($q) => $q->where('user_id', $laura->id));

$enBolsa = $delEquipo()->where('status', 'pending')->whereDoesntHave('assignments')->first();
$enRevision = $delEquipo()->where('status', 'in_review')->first();
$enCurso = $propios()->where('status', 'in_progress')->first()
    ?? $propios()->whereNotIn('status', ['completed', 'cancelled'])->first()
    ?? $delEquipo()->where('status', 'in_progress')->first();

if ($enCurso !== null) {
    // Quien protagoniza las guías es responsable de este ticket.
    $enCurso->assignments()->where('user_id', '!=', $laura->id)->where('role', 'responsable')->update(['user_id' => $laura->id]);
    app(CommentService::class)->add($diego, $enCurso, '¿Pudiste revisar el equipo? Comparte una foto cuando termines.');
    app(CommentService::class)->add($laura, $enCurso, 'Sí, ya quedó revisado. Falta la pieza de repuesto.');
}

$actividad = Activity::query()->where('team_id', $equipo->id)->whereNull('recurrence_rule')
    ->whereHas('subtasks')->whereNotIn('status', ['completed', 'cancelled'])->first()
    ?? Activity::query()->where('team_id', $equipo->id)->whereNull('recurrence_rule')->first();
$plantilla = Activity::query()->whereNotNull('recurrence_rule')->first();

$chat = app(ChatService::class);
$directo = $chat->startDirect($diego, $laura);
$chat->send($diego, $directo, 'Buenos días, Laura. ¿Cómo va el reporte de la estación?');
$chat->send($laura, $directo, 'Buenos días. Lo envío antes de las 2.');
$chat->send($diego, $directo, 'Perfecto, gracias.');

$coordinacion = $equipo->coordination_id;
$correo = null;
foreach ([
    ['mantenimiento@proveedor.ejemplo.mx', 'Proveedor de limpieza', 'Programación de visita de mantenimiento', "Buen día:\n\nSolicitamos confirmar fecha para la visita de mantenimiento de este mes.\n\nSaludos."],
    ['atencion@ejemplo.mx', 'Atención a usuarios', 'Reporte de luminaria dañada en andén', "Se reporta una luminaria apagada en el andén 1."],
    ['logistica@ejemplo.mx', 'Logística', 'Entrega de material programada', "La entrega del material llegará el próximo lunes por la mañana."],
] as $i => [$de, $nombre, $asunto, $cuerpo]) {
    $nuevo = (new IncomingEmail)->forceFill(['status' => 'pending_review',
        'coordination_id' => $coordinacion, 'message_id' => "capturas-{$i}@ejemplo.mx",
        'from_email' => $de, 'from_name' => $nombre, 'subject' => $asunto, 'body' => $cuerpo,
        'received_at' => now()->subHours($i * 5 + 1),
    ]);
    $nuevo->save();
    $correo ??= $nuevo;
}

if (Category::query()->count() === 0) {
    Category::create(['name' => 'Soporte técnico', 'active' => true]);
}

// Los avisos ya creados conservan los nombres y títulos genéricos: se ajustan igual.
foreach (DB::table('notifications')->get() as $aviso) {
    $datos = $aviso->data;
    foreach (['Coordinador Operaciones' => 'Diego Hernández', 'Coordinador Mantenimiento' => 'Elena Vargas',
        'Jefe Demo' => 'Marco Peña', '[Demo] ' => '', ' (Operaciones)' => '', ' (Mantenimiento)' => ''] as $antes => $despues) {
        $datos = str_replace($antes, $despues, $datos);
    }
    DB::table('notifications')->where('id', $aviso->id)->update(['data' => $datos]);
}

echo json_encode([
    'bolsa' => $enBolsa?->id,
    'revision' => $enRevision?->id,
    'curso' => $enCurso?->id,
    'actividad' => $actividad?->id,
    'plantilla' => $plantilla?->id,
    'correo' => $correo?->id,
    'usuario' => $laura->id,
    'equipo' => $equipo->id,
    'coordinacion' => $coordinacion,
    'conversacion' => $directo->id,
]);
