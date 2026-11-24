<?php

declare(strict_types=1);

namespace App\Services\Calendario;

use App\Enums\CalendarioTipo;
use App\Enums\UserStatus;
use App\Models\CalendarioEvento;
use App\Models\User;
use App\Services\Ecosystem\NotificationHubService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Alta, cambio y baja de entradas del calendario oficial, con auditoría y avisos a todas las cuentas activas
 * (fuente `calendario`, que cada persona puede silenciar). Los avisos son idempotentes por `ref`.
 */
final class CalendarioService
{
    public const FUENTE = 'calendario';

    public function __construct(private readonly NotificationHubService $hub) {}

    /**
     * @param  array<string, mixed>  $datos  Atributos ya validados (`CalendarioEvento::$fillable`, instantes en UTC).
     */
    public function crear(array $datos, User $autor): CalendarioEvento
    {
        $evento = new CalendarioEvento($datos);
        $evento->creado_por = $autor->getKey();
        $evento->actualizado_por = $autor->getKey();
        $evento->save();

        activity('calendario')->performedOn($evento)->causedBy($autor)->event('created')
            ->withProperties(['tipo' => $evento->tipo->value, 'inicia_at' => $evento->inicia_at->toIso8601String()])
            ->log('created');

        $this->avisar($evento, __('portal.calendar.notice.new'));

        return $evento;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(CalendarioEvento $evento, array $datos, User $autor): CalendarioEvento
    {
        $evento->fill($datos);
        $cambioAgenda = $evento->isDirty(['titulo', 'tipo', 'inicia_at', 'termina_at', 'todo_el_dia', 'lugar']);

        if ($cambioAgenda) {
            // Si cambió la fecha, el recordatorio de un día antes debe volver a salir.
            $evento->recordado_at = null;
        }

        $evento->actualizado_por = $autor->getKey();
        $evento->save();

        activity('calendario')->performedOn($evento)->causedBy($autor)->event('updated')->log('updated');

        if ($cambioAgenda) {
            $this->avisar($evento, __('portal.calendar.notice.changed'));
        }

        return $evento;
    }

    public function eliminar(CalendarioEvento $evento, User $autor): void
    {
        // Solo se avisa la cancelación de lo que la gente ya sabía y aún no ocurre.
        $avisarCancelacion = $evento->avisado_at !== null && $evento->inicia_at->isFuture();

        activity('calendario')->performedOn($evento)->causedBy($autor)->event('deleted')->log('deleted');
        $evento->delete();

        if ($avisarCancelacion) {
            $this->enviar(
                __('portal.calendar.notice.cancelled').': '.$evento->titulo,
                $this->cuando($evento),
                'cal:'.$evento->getKey().':cancelado',
            );
        }
    }

    /** Recordatorio de las entradas que empiezan en `$dia` (fecha local); una sola vez por entrada. Devuelve cuántas. */
    public function recordar(CarbonImmutable $dia): int
    {
        $zona = config('app.display_timezone');
        $desde = $dia->setTimezone($zona)->startOfDay()->utc();
        $hasta = $desde->addDay();

        $pendientes = CalendarioEvento::query()
            ->whereNull('recordado_at')
            ->where('tipo', '!=', CalendarioTipo::Inhabil->value)
            ->where('inicia_at', '>=', $desde)
            ->where('inicia_at', '<', $hasta)
            ->orderBy('inicia_at')
            ->get();

        foreach ($pendientes as $evento) {
            $this->enviar(
                __('portal.calendar.notice.reminder').': '.$evento->titulo,
                $this->cuando($evento),
                'cal:'.$evento->getKey().':recordatorio:'.$evento->inicia_at->timestamp,
            );
            $evento->forceFill(['recordado_at' => now()])->save();
        }

        return $pendientes->count();
    }

    private function avisar(CalendarioEvento $evento, string $prefijo): void
    {
        $this->enviar(
            $prefijo.': '.$evento->titulo,
            $this->cuando($evento),
            'cal:'.$evento->getKey().':v'.$evento->updated_at->timestamp,
        );

        $evento->forceFill(['avisado_at' => now()])->saveQuietly();
    }

    private function enviar(string $titulo, string $cuerpo, string $ref): void
    {
        $emails = User::query()->where('status', UserStatus::Active->value)->pluck('email')->all();

        if ($emails === []) {
            return;
        }

        $this->hub->send(self::FUENTE, $emails, Str::limit($titulo, 60), Str::limit($cuerpo, 120), '/calendario', $ref, 'calendario');
    }

    /** «sáb 5 dic, 10:00» o «sáb 5 dic» si es de día completo; con «lugar» al final si existe. */
    private function cuando(CalendarioEvento $evento): string
    {
        $inicio = $evento->inicioLocal()->locale('es');
        $texto = $evento->todo_el_dia ? $inicio->isoFormat('ddd D MMM') : $inicio->isoFormat('ddd D MMM, HH:mm');

        return $evento->lugar !== null && $evento->lugar !== '' ? $texto.' · '.$evento->lugar : $texto;
    }
}
