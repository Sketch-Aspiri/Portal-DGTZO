<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CalendarioTipo;
use App\Http\Requests\StoreCalendarioEventoRequest;
use App\Models\CalendarioEvento;
use App\Services\Calendario\CalendarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Alta, cambio y baja de entradas del calendario oficial: solo quien administra el portal (Gate `manage-calendar`). */
class CalendarioEventoController extends Controller
{
    public function __construct(private readonly CalendarioService $calendario) {}

    public function create(): View
    {
        return view('calendario.form', ['evento' => new CalendarioEvento(['tipo' => CalendarioTipo::Evento]), 'tipos' => CalendarioTipo::cases()]);
    }

    public function store(StoreCalendarioEventoRequest $request): RedirectResponse
    {
        $evento = $this->calendario->crear($request->atributos(), $request->user());

        return $this->volver($evento, __('portal.calendar.saved'));
    }

    public function edit(CalendarioEvento $evento): View
    {
        return view('calendario.form', ['evento' => $evento, 'tipos' => CalendarioTipo::cases()]);
    }

    public function update(StoreCalendarioEventoRequest $request, CalendarioEvento $evento): RedirectResponse
    {
        $evento = $this->calendario->actualizar($evento, $request->atributos(), $request->user());

        return $this->volver($evento, __('portal.calendar.saved'));
    }

    public function destroy(CalendarioEvento $evento): RedirectResponse
    {
        $this->calendario->eliminar($evento, request()->user());

        return $this->volver($evento, __('portal.calendar.deleted'));
    }

    private function volver(CalendarioEvento $evento, string $mensaje): RedirectResponse
    {
        return redirect()->route('calendar.index', ['mes' => $evento->inicioLocal()->format('Y-m')])->with('status', $mensaje);
    }
}
