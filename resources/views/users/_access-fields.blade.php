{{--
    Campos de acceso por subsistema (rol y ubicación), compartidos por la aprobación y por "dar acceso".
    Variables: $catalogo (config), $catalogos (catálogo vivo de cada subsistema o null), $esAdministrador,
    $omitir (claves de subsistemas que no se ofrecen).
--}}
@foreach ($catalogo as $clave => $sistema)
    @continue(in_array($clave, $omitir ?? [], true))
    @php($cat = $catalogos[$clave] ?? null)
    @php($privilegiados = $sistema['roles_privilegiados'] ?? [])
    <fieldset class="rounded-lg border p-4 space-y-3" @disabled($cat === null)>
        <label class="flex items-center gap-2 font-medium">
            <input type="checkbox" name="accesos[{{ $clave }}][activo]" value="1" @checked(old("accesos.$clave.activo")) @disabled($cat === null)>
            {{ $sistema['nombre'] }}
        </label>

        @if ($cat === null)
            <p class="text-sm text-red-700">{{ __('portal.catalog_unavailable') }}</p>
        @else
            <div>
                <x-input-label :for="'rol-'.$clave" :value="__('portal.approvals.role')" />
                <select id="rol-{{ $clave }}" name="accesos[{{ $clave }}][rol]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @foreach ($sistema['roles'] as $rolClave => $rolNombre)
                        @if ($esAdministrador || ! in_array($rolClave, $privilegiados, true))
                            <option value="{{ $rolClave }}" @selected(old("accesos.$clave.rol") === $rolClave)>{{ $rolNombre }}</option>
                        @endif
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get("accesos.$clave.rol")" class="mt-1" />
                @if (! $esAdministrador && $privilegiados !== [])
                    <p class="mt-1 text-xs text-gray-500">{{ __('portal.approvals.privileged_hint') }}</p>
                @endif
            </div>

            @if ($clave === 'tickets')
                <div>
                    <p class="text-sm font-medium">{{ __('portal.approvals.teams') }}</p>
                    <p class="text-xs text-gray-500">{{ __('portal.approvals.teams_help') }}</p>
                    @foreach (collect($cat['equipos'] ?? [])->groupBy(fn ($e) => ($e['estacion'] ?? '—').' › '.($e['coordinacion'] ?? '—')) as $grupo => $equipos)
                        <div class="mt-2">
                            <p class="text-xs font-semibold text-gray-600">{{ $grupo }}</p>
                            @foreach ($equipos as $equipo)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="accesos[tickets][team_ids][]" value="{{ $equipo['id'] }}" @checked(in_array($equipo['id'], (array) old('accesos.tickets.team_ids', [])))>
                                    {{ $equipo['nombre'] }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                    <x-input-error :messages="$errors->get('accesos.tickets.team_ids')" class="mt-1" />
                </div>
                <div>
                    <p class="text-sm font-medium">{{ __('portal.approvals.coordinations') }}</p>
                    <p class="text-xs text-gray-500">{{ __('portal.approvals.coordinations_help') }}</p>
                    @foreach ($cat['coordinaciones'] ?? [] as $coordinacion)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="accesos[tickets][coordination_ids][]" value="{{ $coordinacion['id'] }}" @checked(in_array($coordinacion['id'], (array) old('accesos.tickets.coordination_ids', [])))>
                            {{ $coordinacion['estacion'] ?? '—' }} › {{ $coordinacion['nombre'] }}
                        </label>
                    @endforeach
                    <x-input-error :messages="$errors->get('accesos.tickets.coordination_ids')" class="mt-1" />
                </div>
            @elseif ($clave === 'dashboard')
                <div>
                    <x-input-label for="estacion-dashboard" :value="__('portal.approvals.station')" />
                    <select id="estacion-dashboard" name="accesos[dashboard][estacion_id]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($cat['estaciones'] ?? [] as $estacion)
                            <option value="{{ $estacion['id'] }}" @selected((int) old('accesos.dashboard.estacion_id') === $estacion['id'])>{{ $estacion['nombre'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">{{ __('portal.approvals.station_help') }}</p>
                    <x-input-error :messages="$errors->get('accesos.dashboard.estacion_id')" class="mt-1" />
                </div>
                @if (! empty($cat['areas']))
                    <div>
                        <x-input-label for="area-dashboard" :value="__('portal.approvals.area')" />
                        <select id="area-dashboard" name="accesos[dashboard][area]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($cat['areas'] as $area)
                                <option value="{{ $area['clave'] }}" @selected(old('accesos.dashboard.area') === $area['clave'])>{{ $area['nombre'] }}</option>
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
