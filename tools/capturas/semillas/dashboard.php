<?php

/*
 * Datos ficticios para las capturas del Dashboard. Se ejecuta sobre una base desechable ya migrada y sembrada.
 * Uso: php dashboard.php <carpeta del Dashboard>   Imprime en JSON los registros que usa el guion.
 */

use App\Models as M;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

$raiz = rtrim($argv[1] ?? '', '/\\');
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Candado: nunca sobre una base que no sea de capturas.
$base = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($base, '_capturas') || ! app()->environment('local')) {
    fwrite(STDERR, "Base no permitida para las capturas: {$base}\n");
    exit(1);
}

$crear = function (string $modelo, array $datos) {
    $m = new $modelo;
    $m->forceFill($datos)->save();

    return $m;
};

$hoy = Carbon::now('America/Cancun')->startOfDay();
$anio = $hoy->year;
$mes = $hoy->month;
$estaciones = M\Estacion::query()->whereIn('nombre', ['Edificio Zonal Este', 'Puerto Morelos', 'Tulum', 'Bacalar', 'Chetumal'])->get()->keyBy('nombre');
$tulum = $estaciones['Tulum'];

// Personal ficticio.
$nombres = ['Laura Gómez Díaz', 'Iván Castillo Reyes', 'Mariana Torres Luna', 'Carlos Ortega Ruiz', 'Patricia León Mora', 'Raúl Méndez Gil',
    'Gabriela Soto Peña', 'Hugo Ramírez Cano', 'Nadia Cruz Vera', 'Óscar Díaz Ríos', 'Elena Vargas Paz', 'Julia Campos Sol',
    'Diego Hernández Lira', 'Sofía Navarro Rey', 'Andrea Fuentes Mar', 'Luis Aguilar Toro', 'Karla Ibarra Nieto', 'Mario Salas Prado',
    'Teresa Quiroz Vidal', 'Pablo Rentería Cruz', 'Verónica Ochoa Lara', 'Samuel Peralta Ruiz', 'Lucía Bravo Cortés', 'Julián Escobar Rivas', 'Regina Montes Ávila'];
$puestos = ['Jefatura de estación', 'Auxiliar administrativo', 'Técnico de mantenimiento', 'Atención a usuarios', 'Seguridad'];
$personal = collect();
$i = 0;
foreach ($estaciones->values() as $e) {
    foreach (range(0, 4) as $n) {
        $nacimiento = Carbon::create(1980 + ($i % 20), $i % 3 === 0 ? $mes : (($i % 12) + 1), $i === 0 ? $hoy->day : (($i * 3) % 27) + 1);
        $personal->push($crear(M\Empleado::class, [
            'no_empleado' => sprintf('EJ-%03d', $i + 1), 'estatus' => 'activo', 'tipo_plaza' => $n === 0 ? 'permanente' : 'eventual',
            'estacion_id' => $e->id, 'estacion_codigo' => $e->codigo, 'orden_origen' => $i + 1, 'orden_oficio' => $n + 1,
            'plaza_actual' => sprintf('PZ-%03d', $i + 1), 'nombre_completo' => $nombres[$i], 'puesto' => $puestos[$n], 'nivel_plaza' => 'N'.($n + 1),
            'ultimo_grado_estudios' => 'Licenciatura', 'fecha_ingreso' => '2024-0'.(($i % 8) + 1).'-15', 'telefono' => '9840000000',
            'correo' => "persona{$i}@ejemplo.mx", 'fecha_nacimiento' => $nacimiento->toDateString(), 'tipo_sangre' => 'O+',
        ]));
        $i++;
    }
}
$crear(M\Empleado::class, ['estatus' => 'vacante', 'estacion_id' => $tulum->id, 'estacion_codigo' => $tulum->codigo, 'plaza_actual' => 'PZ-099', 'puesto' => 'Auxiliar administrativo', 'orden_origen' => 99]);
$deTulum = $personal->where('estacion_id', $tulum->id)->values();

$cuenta = function (string $nombre, string $correo, string $rol, ?M\Estacion $estacion = null, ?M\Empleado $ficha = null) use ($crear): M\User {
    $u = $crear(M\User::class, [
        'name' => $nombre, 'email' => $correo, 'password' => Hash::make('Clave-Segura-2026!'), 'email_verified_at' => now(),
        'estacion_id' => $estacion?->id, 'empleado_id' => $ficha?->id,
    ]);
    $u->assignRole($rol);

    return $u;
};
$jefe = $cuenta('Marco Peña', 'marco.pena@ejemplo.mx', 'Jefe de Zona');
$admin = $cuenta('Alejandra Ruiz', 'alejandra.ruiz@ejemplo.mx', 'Administrador');
$estacion = $cuenta('Estación Tulum', 'estacion.tulum@ejemplo.mx', 'Estación', $tulum);
$propia = $cuenta('Iván Castillo', 'ivan.castillo@ejemplo.mx', 'Personal', null, $deTulum[1]);

// Asistencia de los últimos días y de hoy (Chetumal queda pendiente hoy).
foreach (range(0, 6) as $atras) {
    $fecha = $hoy->copy()->subDays($atras);
    foreach ($personal as $k => $p) {
        if ($atras === 0 && $p->estacion_id === $estaciones['Chetumal']->id) {
            continue;
        }
        $estatus = match (true) {
            $k % 11 === 3 => 'vacaciones', $k % 13 === 5 => 'licencia_medica', $k % 7 === 2 => 'descanso', $k % 17 === 8 => 'comision', default => 'presente',
        };
        $crear(M\RegistroDiario::class, [
            'empleado_id' => $p->id, 'estacion_id' => $p->estacion_id, 'fecha' => $fecha->toDateString(), 'estatus' => $estatus,
            'turno' => $k % 2 ? 'segundo' : 'primer', 'registrado_por' => $estacion->id,
            'fecha_inicio' => in_array($estatus, ['vacaciones', 'licencia_medica', 'comision'], true) ? $hoy->copy()->subDays(7)->toDateString() : null,
            'fecha_fin' => in_array($estatus, ['vacaciones', 'licencia_medica', 'comision'], true) ? $hoy->copy()->addDays(3)->toDateString() : null,
        ]);
    }
}

// Rol de horarios del mes.
$horario = $crear(M\HorarioPeriodo::class, ['anio' => $anio, 'mes' => $mes, 'version' => 1, 'estado' => 'vigente', 'origen' => 'sistema', 'publicado_at' => now()]);
$turnos = ['07:00 - 15:00', '15:00 - 23:00', '07:00 - 15:00', 'DESCANSO'];
foreach (range(1, $hoy->daysInMonth) as $dia) {
    foreach ($personal as $k => $p) {
        $jornada = ($dia + $k) % 7 === 0 ? 'DESCANSO' : $turnos[$k % 3];
        $comida = $jornada === 'DESCANSO' ? null : ($k % 3 === 1 ? '19:00 - 20:00' : '12:00 - 13:00');
        $crear(M\HorarioTurno::class, [
            'periodo_id' => $horario->id, 'estacion_id' => $p->estacion_id, 'no_empleado' => $p->no_empleado, 'empleado_id' => $p->id,
            'nombre' => $p->nombre_completo, 'puesto' => $p->puesto, 'fecha' => Carbon::create($anio, $mes, $dia)->toDateString(),
            'jornada' => $jornada, 'comida' => $comida, 'jornada_oficial' => $jornada, 'comida_oficial' => $comida, 'origen' => 'oficial',
        ]);
    }
}

// Una solicitud de cambio de horario por autorizar.
$turno = M\HorarioTurno::query()->where('estacion_id', $tulum->id)->whereDate('fecha', $hoy->copy()->endOfMonth())->firstOrFail();
$crear(M\HorarioSolicitud::class, [
    'periodo_id' => $horario->id, 'estacion_id' => $tulum->id, 'turno_id' => $turno->id, 'solicitante_id' => $estacion->id,
    'no_empleado' => $turno->no_empleado, 'nombre' => $turno->nombre, 'fecha' => $turno->fecha, 'tipo' => 'cambio_turno',
    'jornada_origen' => $turno->jornada, 'comida_origen' => $turno->comida, 'nueva_jornada' => '15:00 - 23:00', 'nueva_comida' => '19:00 - 20:00',
    'motivo' => 'Cita médica por la mañana.', 'estado' => 'pendiente',
]);

// Rol de vacaciones del año.
$rol = $crear(M\VacacionPeriodo::class, ['anio' => $anio, 'version' => 1, 'estado' => 'vigente', 'publicado_at' => now()]);
foreach ($personal as $k => $p) {
    $persona = $crear(M\VacacionPersona::class, [
        'periodo_id' => $rol->id, 'estacion_id' => $p->estacion_id, 'no_empleado' => $p->no_empleado, 'empleado_id' => $p->id,
        'nombre' => $p->nombre_completo, 'puesto' => $p->puesto, 'dias_otorgados' => 20, 'orden' => $k + 1,
    ]);
    foreach (range(1, 4) as $t) {
        $inicio = Carbon::create($anio, ($t - 1) * 3 + 1 + ($k % 3), 1 + ($k % 4) * 5);
        $fin = $inicio->copy()->addDays(4);
        $crear(M\VacacionTramo::class, [
            'periodo_id' => $rol->id, 'persona_id' => $persona->id, 'estacion_id' => $p->estacion_id, 'trimestre' => $t,
            'inicio' => $inicio->toDateString(), 'fin' => $fin->toDateString(), 'dias' => 5, 'origen' => 'oficial',
            'inicio_oficial' => $inicio->toDateString(), 'fin_oficial' => $fin->toDateString(), 'dias_oficial' => 5,
        ]);
    }
}

// Selección del año siguiente: catálogo y algunas solicitudes.
$catalogo = [];
foreach (range(1, 4) as $t) {
    foreach (range(0, 5) as $n) {
        $inicio = Carbon::create($anio + 1, ($t - 1) * 3 + 1, 1)->addWeeks($n * 2)->startOfWeek()->addWeek();
        $catalogo[$t][] = $crear(M\VacacionCatalogoPeriodo::class, [
            'anio' => $anio + 1, 'inicio' => $inicio->toDateString(), 'fin' => $inicio->copy()->addDays(6)->toDateString(),
            'dias' => 5, 'trimestre' => $t, 'autorizado' => true,
        ]);
    }
}
foreach ([[0, 'enviada'], [2, 'enviada'], [3, 'autorizada']] as [$n, $estado]) {
    $plan = $crear(M\VacacionPlan::class, [
        'anio' => $anio + 1, 'empleado_id' => $deTulum[$n]->id, 'estacion_id' => $tulum->id, 'dias_correspondientes' => 20,
        'estado' => $estado, 'enviada_at' => now()->subDay(),
    ]);
    foreach (range(1, 4) as $t) {
        $crear(M\VacacionSeleccion::class, ['plan_id' => $plan->id, 'trimestre' => $t, 'catalogo_periodo_id' => $catalogo[$t][$n]->id]);
    }
}

// Insumos: catálogo, proveedores, inventario y solicitudes.
$proveedores = collect(['Suministros del Caribe (ejemplo)', 'Ferretería Peninsular (ejemplo)'])->map(fn ($n, $k) => $crear(M\Proveedor::class, ['nombre' => $n, 'iva' => $k ? 16 : 8, 'giro' => 'Material de mantenimiento', 'ubicacion' => 'Quintana Roo']));
$articulos = [['Cinta de aislar', 'Pieza', 28], ['Foco LED 12 W', 'Pieza', 55], ['Limpiador multiusos', 'Litro', 42], ['Guantes de trabajo', 'Par', 65], ['Brocha de 3 pulgadas', 'Pieza', 48], ['Cable calibre 12', 'Metro', 19]];
$catalogoInsumos = collect($articulos)->map(fn ($a, $k) => $crear(M\CatalogoInsumo::class, [
    'descripcion' => $a[0], 'unidad' => $a[1], 'codigo' => sprintf('INS-%03d', $k + 1), 'ultimo_precio' => $a[2],
    'partida_presupuestal' => '29101', 'proveedor' => $proveedores[$k % 2]->nombre, 'activo' => true,
]));
foreach ($articulos as $k => $a) {
    $crear(M\InventarioEstacion::class, ['estacion_id' => $tulum->id, 'tipo' => $k < 3 ? 'HERRAMIENTA' : 'CONSUMIBLE', 'clave' => 'ej-'.$k, 'descripcion' => $a[0], 'unidad' => $a[1], 'cantidad' => 3 + $k, 'primer_mes' => sprintf('%d-%02d', $anio, $mes), 'ultimo_mes' => sprintf('%d-%02d', $anio, $mes), 'fuente' => 'resguardos']);
}
$siguiente = $hoy->copy()->startOfMonth()->addMonth();
$solicitudes = [];
foreach ([['Tulum', 'borrador'], ['Bacalar', 'enviada'], ['Chetumal', 'observada'], ['Puerto Morelos', 'aprobada']] as [$nombre, $estado]) {
    $s = $crear(M\SolicitudInsumo::class, [
        'estacion_id' => $estaciones[$nombre]->id, 'anio' => $siguiente->year, 'mes' => $siguiente->month, 'tipo_servicio' => '1er escalón',
        'lugar_trabajo' => "Estación {$nombre}", 'estado' => $estado, 'enviada_at' => $estado === 'borrador' ? null : now()->subDay(),
        'comentario_zona' => $estado === 'observada' ? 'Revisa la cantidad de focos solicitada.' : null,
    ]);
    foreach ($catalogoInsumos->take(4) as $k => $c) {
        $crear(M\SolicitudInsumoPartida::class, [
            'solicitud_insumo_id' => $s->id, 'numero' => $k + 1, 'cantidad' => $k + 2, 'unidad' => $c->unidad, 'descripcion' => $c->descripcion,
            'justificacion' => 'Mantenimiento preventivo', 'catalogo_insumo_id' => $c->id, 'codigo' => $c->codigo,
            'precio_unitario' => $c->ultimo_precio, 'precio_estimado' => $c->ultimo_precio * ($k + 2), 'partida_presupuestal' => '29101', 'proveedor' => $c->proveedor,
        ]);
    }
    $solicitudes[$estado] = $s->id;
}

// Papelería.
$papeles = collect(['Hojas tamaño carta', 'Bolígrafo azul', 'Folder tamaño oficio', 'Marcador permanente'])->map(fn ($n) => $crear(M\CatalogoPapeleria::class, ['descripcion' => $n, 'activo' => true]));
$papeleria = [];
foreach ([['Tulum', 'borrador'], ['Bacalar', 'enviada'], ['Chetumal', 'aprobada']] as [$nombre, $estado]) {
    $s = $crear(M\SolicitudPapeleria::class, ['estacion_id' => $estaciones[$nombre]->id, 'anio' => $anio, 'mes' => $mes, 'estado' => $estado, 'enviada_at' => $estado === 'borrador' ? null : now()]);
    foreach ($papeles as $k => $p) {
        $crear(M\SolicitudPapeleriaPartida::class, ['solicitud_papeleria_id' => $s->id, 'numero' => $k + 1, 'catalogo_papeleria_id' => $p->id, 'articulo' => $p->descripcion, 'unidad' => 'Pieza', 'cantidad' => $k + 1]);
    }
    $papeleria[$estado] = $s->id;
}

// Agua.
$agua = [];
foreach ([['Tulum', 'borrador'], ['Bacalar', 'enviada'], ['Chetumal', 'aprobada']] as [$nombre, $estado]) {
    $r = $crear(M\AguaRegistro::class, [
        'estacion_id' => $estaciones[$nombre]->id, 'anio' => $anio, 'mes' => $mes, 'estado' => $estado, 'frecuencia' => 'Semanal',
        'consumo_mensual' => 80, 'costo_unitario' => 38, 'garrafones_propios' => 10, 'garrafones_comodato' => 5, 'dia_entrega' => 'Lunes',
        'enviada_at' => $estado === 'borrador' ? null : now(),
    ]);
    foreach ([3, 10, 17] as $dia) {
        $crear(M\AguaCompra::class, ['registro_id' => $r->id, 'fecha' => Carbon::create($anio, $mes, min($dia, $hoy->day))->toDateString(), 'garrafones' => 20, 'costo_unitario' => 38, 'monto' => 760]);
    }
    $agua[$estado] = $r->id;
}

// Flujo de pasajeros.
foreach (range(1, 40) as $atras) {
    foreach ($estaciones->except('Edificio Zonal Este')->values() as $k => $e) {
        $base = 120 + $k * 60 + (($atras * 17) % 45);
        $crear(M\AfluenciaDiaria::class, ['estacion_id' => $e->id, 'fecha' => $hoy->copy()->subDays($atras)->toDateString(), 'abordan' => $base, 'boletos' => $base + 12, 'capturado_por' => $estacion->id]);
    }
}

// PTAR.
$ptar = [];
foreach ([['Tulum', 'borrador'], ['Bacalar', 'enviada'], ['Chetumal', 'aprobada']] as $k => [$nombre, $estado]) {
    $planta = $crear(M\PtarPlanta::class, ['estacion_id' => $estaciones[$nombre]->id, 'numero' => 1, 'tramo' => 'Tramo '.($k + 5), 'tipo' => 'Lodos activados', 'normas' => ['NOM-001-SEMARNAT-2021'], 'empresa_operadora' => 'Operadora de ejemplo', 'activa' => true]);
    $r = $crear(M\PtarRegistro::class, [
        'estacion_id' => $estaciones[$nombre]->id, 'anio' => $anio, 'mes' => $mes, 'estado' => $estado, 'modo' => $k === 1 ? 'manual' : 'automatico',
        'operador' => 'Operadora de ejemplo', 'observaciones_generales' => 'Operación estable durante el periodo.', 'enviada_at' => $estado === 'borrador' ? null : now(),
    ]);
    foreach (['Bomba de agua residual', 'Soplador', 'Tablero de control'] as $n => $equipo) {
        $crear(M\PtarEquipo::class, ['registro_id' => $r->id, 'equipo' => $equipo, 'estatus' => $n === 1 && $k === 1 ? 'parcial' : 'operando']);
    }
    $crear(M\PtarHallazgo::class, ['planta_id' => $planta->id, 'registro_origen_id' => $r->id, 'tipo' => 'problematica', 'equipo' => 'Soplador', 'descripcion' => 'Ruido fuera de lo normal en el soplador.', 'propuesta' => 'Programar revisión de baleros.', 'prioridad' => 'media', 'estado' => $k ? 'en_proceso' : 'abierto', 'fecha_detectado' => $hoy->copy()->subDays(5)->toDateString(), 'capturo_id' => $estacion->id]);
    $ptar[$estado] = $r->id;
}

echo json_encode([
    'tulum' => $tulum->id, 'empleado' => $deTulum[1]->id, 'insumos' => $solicitudes, 'papeleria' => $papeleria, 'agua' => $agua, 'ptar' => $ptar,
    'usuario' => $estacion->id, 'anio' => $anio, 'mes' => $mes,
]);
