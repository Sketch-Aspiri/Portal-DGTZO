<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $user->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 px-4 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-700">{{ session('status') }}</p>
            @endif
            @if ($errors->has('business'))
                <p class="text-sm text-red-700">{{ $errors->first('business') }}</p>
            @endif

            <div class="bg-white shadow rounded-xl p-6">
                <p class="text-sm text-gray-600">{{ $user->email }}</p>
                <p class="mt-1 text-sm">{{ $user->status->label() }}</p>
            </div>

            @if ($user->accesos->isNotEmpty())
                <div class="bg-white shadow rounded-xl p-6">
                    <h3 class="font-semibold">{{ __('portal.approvals.accesses') }}</h3>
                    <ul class="mt-3 divide-y text-sm">
                        @foreach ($user->accesos as $acceso)
                            @php($sistema = $catalogo[$acceso->subsistema] ?? null)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span>
                                    <strong>{{ $sistema['nombre'] ?? $acceso->subsistema }}</strong>
                                    — {{ $sistema['roles'][$acceso->rol] ?? $acceso->rol }}
                                </span>
                                <span class="flex items-center gap-3">
                                    <span @class([
                                        'text-green-700' => $acceso->aprovisionamiento === 'ok',
                                        'text-amber-700' => in_array($acceso->aprovisionamiento, ['pendiente', 'conflicto'], true),
                                        'text-red-700' => $acceso->aprovisionamiento === 'error',
                                    ])>{{ __('portal.provision.state.'.$acceso->aprovisionamiento) }}</span>
                                    @if ($acceso->aprovisionamiento === 'error')
                                        <form method="POST" action="{{ route('users.retry', [$user, $acceso->subsistema]) }}">
                                            @csrf
                                            <button type="submit" class="underline">{{ __('portal.approvals.retry') }}</button>
                                        </form>
                                    @endif
                                </span>
                                @if ($acceso->ultimo_error)
                                    <span class="basis-full text-xs text-gray-500">{{ $acceso->ultimo_error }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($user->status === \App\Enums\UserStatus::Pending)
                <form method="POST" action="{{ route('users.approve', $user) }}" class="bg-white shadow rounded-xl p-6 space-y-4">
                    @csrf
                    <h3 class="font-semibold">{{ __('portal.approvals.grant') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('portal.approvals.grant_help') }}</p>
                    <x-input-error :messages="$errors->get('accesos')" />

                    @foreach ($catalogo as $clave => $sistema)
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
                                @endif
                            @endif
                        </fieldset>
                    @endforeach

                    <x-primary-button>{{ __('portal.approvals.approve') }}</x-primary-button>
                </form>

                <form method="POST" action="{{ route('users.reject', $user) }}" class="bg-white shadow rounded-xl p-6 space-y-3">
                    @csrf
                    <x-input-label for="reason" :value="__('portal.approvals.reason')" />
                    <textarea id="reason" name="reason" rows="2" required maxlength="500" class="block w-full rounded-md border-gray-300 text-sm">{{ old('reason') }}</textarea>
                    <x-input-error :messages="$errors->get('reason')" />
                    <x-danger-button>{{ __('portal.approvals.reject') }}</x-danger-button>
                </form>
            @endif

            <a href="{{ route('users.index') }}" class="text-sm underline text-gray-700">{{ __('portal.approvals.back') }}</a>
        </div>
    </div>
</x-app-layout>
