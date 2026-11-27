{{--
    Campos de acceso por subsistema (rol y ubicación), compartidos por la aprobación y por "dar acceso".
    Variables: $catalogo (config), $catalogos (catálogo vivo de cada subsistema o null), $esAdministrador,
    $omitir (claves de subsistemas que no se ofrecen), $prefill (clave => rol y contexto actuales, al editar),
    $fijo (true: el sistema ya esta elegido y no se puede desmarcar, al editar),
    $prefijo (para que no se repitan los id cuando el parcial aparece varias veces en la pantalla).
--}}
@foreach ($catalogo as $clave => $sistema)
    @continue(in_array($clave, $omitir ?? [], true))
    @php($cat = $catalogos[$clave] ?? null)
    @php($privilegiados = $sistema['roles_privilegiados'] ?? [])
    @php($pref = isset($prefill[$clave]) ? (array) $prefill[$clave] : (isset($solicitud) && $solicitud ? (array) ($solicitud->accesos[$clave] ?? []) : []))
    @php($pid = $prefijo ?? '')
    <fieldset class="min-w-0 rounded-lg border p-4 space-y-3" @disabled($cat === null)>
        <legend class="sr-only">{{ $sistema['nombre'] }}</legend>
        @if (! empty($fijo))
            <input type="hidden" name="accesos[{{ $clave }}][activo]" value="1">
            <p class="font-medium">{{ $sistema['nombre'] }}</p>
        @else
            <label class="flex min-h-[32px] items-center gap-2 font-medium">
                <input type="checkbox" name="accesos[{{ $clave }}][activo]" value="1" class="rounded border-gray-300 text-brand-teal focus:ring-brand-teal" @checked(old("accesos.$clave.activo", ! empty($pref))) @disabled($cat === null)>
                {{ $sistema['nombre'] }}
            </label>
        @endif

        @if ($cat === null)
            <p class="text-sm text-red-700">{{ __('portal.catalog_unavailable') }}</p>
        @else
            <div>
                <x-input-label :for="$pid.'rol-'.$clave" :value="__('portal.approvals.role')" />
                <select id="{{ $pid }}rol-{{ $clave }}" name="accesos[{{ $clave }}][rol]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @foreach ($sistema['roles'] as $rolClave => $rolNombre)
                        @if ($esAdministrador || ! in_array($rolClave, $privilegiados, true))
                            <option value="{{ $rolClave }}" @selected(old("accesos.$clave.rol", $pref['rol'] ?? null) === $rolClave)>{{ $rolNombre }}</option>
                        @endif
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get("accesos.$clave.rol")" class="mt-1" />
                @if (! $esAdministrador && $privilegiados !== [])
                    <p class="mt-1 text-xs text-gray-500">{{ __('portal.approvals.privileged_hint') }}</p>
                @endif
            </div>

            @if ($clave === 'tickets')
                <fieldset class="min-w-0" aria-describedby="{{ $pid }}equipos-ayuda">
                    <legend class="text-sm font-medium">{{ __('portal.approvals.teams') }}</legend>
                    <p id="{{ $pid }}equipos-ayuda" class="text-xs text-gray-500">{{ __('portal.approvals.teams_help') }}</p>
                    @foreach (collect($cat['equipos'] ?? [])->groupBy(fn ($e) => ($e['estacion'] ?? '—').' › '.($e['coordinacion'] ?? '—')) as $grupo => $equipos)
                        <fieldset class="min-w-0 mt-2">
                            <legend class="text-xs font-semibold text-gray-600">{{ $grupo }}</legend>
                            @foreach ($equipos as $equipo)
                                <label class="flex min-h-[32px] items-center gap-2 text-sm">
                                    <input type="checkbox" class="rounded border-gray-300 text-brand-teal focus:ring-brand-teal" name="accesos[tickets][team_ids][]" value="{{ $equipo['id'] }}" @checked(in_array($equipo['id'], (array) old('accesos.tickets.team_ids', $pref['team_ids'] ?? [])))>
                                    {{ $equipo['nombre'] }}
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                    <x-input-error :messages="$errors->get('accesos.tickets.team_ids')" class="mt-1" />
                </fieldset>
                <fieldset class="min-w-0" aria-describedby="{{ $pid }}coordinaciones-ayuda">
                    <legend class="text-sm font-medium">{{ __('portal.approvals.coordinations') }}</legend>
                    <p id="{{ $pid }}coordinaciones-ayuda" class="text-xs text-gray-500">{{ __('portal.approvals.coordinations_help') }}</p>
                    @foreach ($cat['coordinaciones'] ?? [] as $coordinacion)
                        <label class="flex min-h-[32px] items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded border-gray-300 text-brand-teal focus:ring-brand-teal" name="accesos[tickets][coordination_ids][]" value="{{ $coordinacion['id'] }}" @checked(in_array($coordinacion['id'], (array) old('accesos.tickets.coordination_ids', $pref['coordination_ids'] ?? [])))>
                            {{ $coordinacion['estacion'] ?? '—' }} › {{ $coordinacion['nombre'] }}
                        </label>
                    @endforeach
                    <x-input-error :messages="$errors->get('accesos.tickets.coordination_ids')" class="mt-1" />
                </fieldset>
            @elseif ($clave === 'dashboard')
                <div>
                    <x-input-label :for="$pid.'estacion-dashboard'" :value="__('portal.approvals.station')" />
                    <select id="{{ $pid }}estacion-dashboard" name="accesos[dashboard][estacion_id]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($cat['estaciones'] ?? [] as $estacion)
                            <option value="{{ $estacion['id'] }}" @selected((int) old('accesos.dashboard.estacion_id', $pref['estacion_id'] ?? 0) === $estacion['id'])>{{ $estacion['nombre'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">{{ __('portal.approvals.station_help') }}</p>
                    <x-input-error :messages="$errors->get('accesos.dashboard.estacion_id')" class="mt-1" />
                </div>
                @if (! empty($cat['areas']))
                    <div>
                        <x-input-label :for="$pid.'area-dashboard'" :value="__('portal.approvals.area')" />
                        <select id="{{ $pid }}area-dashboard" name="accesos[dashboard][area]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($cat['areas'] as $area)
                                <option value="{{ $area['clave'] }}" @selected(old('accesos.dashboard.area', $pref['area'] ?? null) === $area['clave'])>{{ $area['nombre'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">{{ __('portal.approvals.area_help') }}</p>
                        <x-input-error :messages="$errors->get('accesos.dashboard.area')" class="mt-1" />
                    </div>
                @endif
            @endif
        @endif
    </fieldset>
@endforeach
