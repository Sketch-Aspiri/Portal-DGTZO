{{--
    Asistente de solicitud de acceso (cuenta pendiente sin solicitud, o editando la enviada).
    Variables: $solicitud (SolicitudAcceso|null), $catalogos (catálogo reducido por subsistema o null).
--}}
@php
    $sistemas = \App\Support\Subsistemas::todos();

    // Solo roles no privilegiados: los amplios los otorga un administrador del portal.
    $roles = collect($sistemas)->map(fn ($s) => collect($s['roles'])->except($s['roles_privilegiados'] ?? [])->all())->all();

    $fields = ['puesto', 'ubicacion', 'telefono', 'funciones', 'justificacion'];
    $initial = old()
        ? array_merge(collect($fields)->mapWithKeys(fn ($f) => [$f => old($f)])->all(), ['accesos' => (array) old('accesos', [])])
        : ($solicitud ? array_merge($solicitud->only($fields), ['accesos' => $solicitud->accesos]) : []);

    $errorStep = null;
    if ($errors->any()) {
        $first = array_key_first($errors->messages());
        $errorStep = match (true) {
            in_array($first, ['puesto', 'ubicacion', 'telefono'], true) => 0,
            str_starts_with((string) $first, 'accesos') => 1,
            in_array($first, ['funciones', 'justificacion'], true) => 2,
            default => 3,
        };
    }

    $config = [
        'initial' => $initial,
        'catalogos' => $catalogos,
        'roles' => $roles,
        'errorStep' => $errorStep,
        'labels' => collect(['req_puesto', 'req_ubicacion', 'req_system', 'req_role', 'req_station', 'req_teams', 'req_coordinations', 'req_functions'])
            ->mapWithKeys(fn ($k) => [$k => __('portal.access_request.'.$k)])->all()
            + collect($sistemas)->mapWithKeys(fn ($s, $k) => ['name_'.$k => $s['nombre']])->all(),
    ];

    $input = 'mt-1 block w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-brand-teal focus:ring-brand-teal';
    $stepNames = __('portal.access_request.steps');
@endphp

<form method="POST" action="{{ route('account.request') }}" x-data="accessWizard(@js($config))" @submit="submit($event)"
      @keydown.enter="if ($event.target.tagName !== 'TEXTAREA' && step < total - 1) { $event.preventDefault(); next(); }"
      class="space-y-4" novalidate>
    @csrf
    @method('PUT')

    {{-- Riel de progreso --}}
    <div class="mb-2" aria-hidden="true">
        <div class="relative h-1.5 overflow-hidden rounded-full bg-brand-green/10">
            <div class="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-brand-teal to-brand-green transition-all duration-500 motion-reduce:transition-none" :style="`width: ${progress}%`"></div>
        </div>
        <ol class="mt-2 flex justify-between text-xs font-medium text-gray-500">
            @foreach ($stepNames as $i => $name)
                <li :class="step >= {{ $i }} ? 'text-brand-green' : ''" class="hidden sm:block">{{ $i + 1 }}. {{ $name }}</li>
            @endforeach
            <li class="sm:hidden text-brand-green" x-text="`{{ __('Paso') }} ${step + 1} / ${total}`"></li>
        </ol>
    </div>

    @if ($errors->any())
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
    @endif

    {{-- 1. Sobre ti --}}
    <x-wizard.step :index="0" :title="__('portal.access_request.about_title')" :help="__('portal.access_request.about_help')">
        <div class="space-y-4">
            <div>
                <x-input-label for="puesto" :value="__('portal.access_request.puesto')" />
                <input id="puesto" name="puesto" type="text" x-model="form.puesto" maxlength="120" autocomplete="organization-title" placeholder="{{ __('portal.access_request.puesto_ph') }}" class="{{ $input }}">
                <p class="mt-1 text-sm text-red-600" x-show="errors.puesto" x-text="errors.puesto"></p>
                <x-input-error :messages="$errors->get('puesto')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="ubicacion" :value="__('portal.access_request.ubicacion')" />
                <input id="ubicacion" name="ubicacion" type="text" x-model="form.ubicacion" maxlength="120" placeholder="{{ __('portal.access_request.ubicacion_ph') }}" class="{{ $input }}">
                <p class="mt-1 text-sm text-red-600" x-show="errors.ubicacion" x-text="errors.ubicacion"></p>
                <x-input-error :messages="$errors->get('ubicacion')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="telefono" :value="__('portal.access_request.telefono')" />
                <input id="telefono" name="telefono" type="tel" x-model="form.telefono" maxlength="30" autocomplete="tel" class="{{ $input }}">
                <x-input-error :messages="$errors->get('telefono')" class="mt-1" />
            </div>
            <div class="flex justify-end">
                <x-primary-button type="button" @click="next()">{{ __('portal.access_request.next') }}</x-primary-button>
            </div>
        </div>
    </x-wizard.step>

    {{-- 2. Sistemas --}}
    <x-wizard.step :index="1" :title="__('portal.access_request.systems_title')" :help="__('portal.access_request.systems_help')">
        <div class="space-y-3">
            <p class="text-sm text-red-600" x-show="errors.sistemas" x-text="errors.sistemas" role="alert"></p>
            <x-input-error :messages="$errors->get('accesos')" class="mt-1" />

            @foreach ($sistemas as $clave => $sistema)
                <div class="rounded-xl border transition-colors duration-300 motion-reduce:transition-none"
                     :class="sys.{{ $clave }}.activo ? 'border-brand-teal/50 bg-brand-mist/50' : 'border-gray-200 bg-white'">
                    <label class="flex min-h-[64px] cursor-pointer items-start gap-3 p-4">
                        <input type="checkbox" name="accesos[{{ $clave }}][activo]" value="1" x-model="sys.{{ $clave }}.activo" @change="toggleSystem('{{ $clave }}')"
                               class="mt-1 h-5 w-5 rounded border-gray-300 text-brand-teal focus:ring-brand-teal">
                        <span>
                            <span class="block font-heading font-semibold text-brand-green">{{ $sistema['nombre'] }}</span>
                            <span class="block text-sm text-gray-600">{{ $sistema['descripcion'] }}</span>
                        </span>
                    </label>

                    {{-- Sub-tarjeta que se despliega al elegir el sistema --}}
                    <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-out motion-reduce:transition-none"
                         :class="sys.{{ $clave }}.activo ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                         :inert="! sys.{{ $clave }}.activo">
                        <div class="min-h-0 overflow-hidden">
                            <div class="space-y-4 border-t border-brand-green/10 p-4">
                                <fieldset>
                                    <legend class="text-sm font-medium text-brand-green">{{ __('portal.access_request.how_use') }}</legend>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @foreach ($roles[$clave] as $rolClave => $rolNombre)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="accesos[{{ $clave }}][rol]" value="{{ $rolClave }}" x-model="sys.{{ $clave }}.rol" class="peer sr-only">
                                                <span class="block min-h-[44px] rounded-lg border border-gray-200 bg-white p-3 text-sm transition peer-checked:border-brand-teal peer-checked:bg-brand-mist peer-checked:ring-1 peer-checked:ring-brand-teal peer-focus-visible:ring-2 peer-focus-visible:ring-brand-teal motion-reduce:transition-none">
                                                    <span class="block font-semibold text-brand-green">{{ $rolNombre }}</span>
                                                    <span class="block text-gray-600">{{ __('portal.access_request.role_help.'.$clave.'.'.$rolClave) }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-sm text-red-600" x-show="errors.{{ $clave }}_rol" x-text="errors.{{ $clave }}_rol"></p>
                                    <x-input-error :messages="$errors->get('accesos.'.$clave.'.rol')" class="mt-1" />
                                </fieldset>

                                @if ($clave === 'dashboard')
                                    <template x-if="hasCatalog('dashboard')">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label for="estacion-dashboard" class="block text-sm font-medium text-brand-green">{{ __('portal.access_request.station') }}</label>
                                                <select id="estacion-dashboard" name="accesos[dashboard][estacion_id]" x-model="sys.dashboard.estacion_id" class="{{ $input }}">
                                                    <option value="">{{ __('portal.access_request.choose') }}</option>
                                                    <template x-for="s in catalogos.dashboard.estaciones" :key="s.id">
                                                        <option :value="String(s.id)" x-text="s.nombre"></option>
                                                    </template>
                                                </select>
                                                <p class="mt-1 text-sm text-red-600" x-show="errors.dashboard_estacion" x-text="errors.dashboard_estacion"></p>
                                                <x-input-error :messages="$errors->get('accesos.dashboard.estacion_id')" class="mt-1" />
                                            </div>
                                            <div x-show="catalogos.dashboard.areas.length > 0">
                                                <label for="area-dashboard" class="block text-sm font-medium text-brand-green">{{ __('portal.access_request.area') }}</label>
                                                <select id="area-dashboard" name="accesos[dashboard][area]" x-model="sys.dashboard.area" class="{{ $input }}">
                                                    <option value="">{{ __('portal.access_request.choose') }}</option>
                                                    <template x-for="a in catalogos.dashboard.areas" :key="a.clave">
                                                        <option :value="a.clave" x-text="a.nombre"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                @else
                                    <template x-if="hasCatalog('tickets')">
                                        <div class="space-y-4">
                                            <div x-show="sys.tickets.rol === 'empleado'">
                                                <p class="text-sm font-medium text-brand-green">{{ __('portal.access_request.teams') }}</p>
                                                <p class="text-xs text-gray-500">{{ __('portal.access_request.teams_help') }}</p>
                                                <template x-for="g in teamGroups()" :key="g.name">
                                                    <div class="mt-2">
                                                        <p class="text-xs font-semibold text-gray-600" x-text="g.name"></p>
                                                        <template x-for="e in g.items" :key="e.id">
                                                            <label class="flex min-h-[36px] items-center gap-2 text-sm">
                                                                <input type="checkbox" name="accesos[tickets][team_ids][]" :value="e.id" x-model="sys.tickets.team_ids" class="h-4 w-4 rounded border-gray-300 text-brand-teal focus:ring-brand-teal">
                                                                <span x-text="e.nombre"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </template>
                                                <p class="mt-1 text-sm text-red-600" x-show="errors.tickets_equipos" x-text="errors.tickets_equipos"></p>
                                            </div>
                                            <div x-show="sys.tickets.rol === 'coordinador'">
                                                <p class="text-sm font-medium text-brand-green">{{ __('portal.access_request.coordinations') }}</p>
                                                <template x-for="c in catalogos.tickets.coordinaciones" :key="c.id">
                                                    <label class="flex min-h-[36px] items-center gap-2 text-sm">
                                                        <input type="checkbox" name="accesos[tickets][coordination_ids][]" :value="c.id" x-model="sys.tickets.coordination_ids" class="h-4 w-4 rounded border-gray-300 text-brand-teal focus:ring-brand-teal">
                                                        <span x-text="`${c.estacion} › ${c.nombre}`"></span>
                                                    </label>
                                                </template>
                                                <p class="mt-1 text-sm text-red-600" x-show="errors.tickets_coord" x-text="errors.tickets_coord"></p>
                                            </div>
                                        </div>
                                    </template>
                                @endif

                                {{-- Catálogo caído: texto libre para que la Zona complete --}}
                                <template x-if="! hasCatalog('{{ $clave }}')">
                                    <div>
                                        <label for="detalle-{{ $clave }}" class="block text-sm font-medium text-brand-green">{{ __('portal.access_request.detail') }}</label>
                                        <input id="detalle-{{ $clave }}" name="accesos[{{ $clave }}][detalle]" type="text" maxlength="300" x-model="sys.{{ $clave }}.detalle" class="{{ $input }}">
                                        <p class="mt-1 text-xs text-gray-500">{{ __('portal.access_request.detail_help') }}</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="flex items-center justify-between pt-1">
                <x-secondary-button type="button" @click="back()">{{ __('portal.access_request.back') }}</x-secondary-button>
                <x-primary-button type="button" @click="next()">{{ __('portal.access_request.next') }}</x-primary-button>
            </div>
        </div>
    </x-wizard.step>

    {{-- 3. Funciones --}}
    <x-wizard.step :index="2" :title="__('portal.access_request.functions_title')" :help="__('portal.access_request.functions_help')">
        <div class="space-y-4">
            <div>
                <x-input-label for="funciones" :value="__('portal.access_request.functions')" />
                <textarea id="funciones" name="funciones" rows="4" maxlength="1500" x-model="form.funciones" placeholder="{{ __('portal.access_request.functions_ph') }}" class="{{ $input }}"></textarea>
                <p class="mt-1 text-xs text-gray-500" x-text="`${form.funciones.length} / 1500`"></p>
                <p class="mt-1 text-sm text-red-600" x-show="errors.funciones" x-text="errors.funciones"></p>
                <x-input-error :messages="$errors->get('funciones')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="justificacion" :value="__('portal.access_request.justification')" />
                <textarea id="justificacion" name="justificacion" rows="2" maxlength="1000" x-model="form.justificacion" class="{{ $input }}"></textarea>
                <x-input-error :messages="$errors->get('justificacion')" class="mt-1" />
            </div>
            <div class="flex items-center justify-between">
                <x-secondary-button type="button" @click="back()">{{ __('portal.access_request.back') }}</x-secondary-button>
                <x-primary-button type="button" @click="next()">{{ __('portal.access_request.next') }}</x-primary-button>
            </div>
        </div>
    </x-wizard.step>

    {{-- 4. Revisa y envía --}}
    <x-wizard.step :index="3" :title="__('portal.access_request.review_title')" :help="__('portal.access_request.review_help')">
        <div class="space-y-3 text-sm">
            <div class="rounded-xl bg-brand-mist p-4">
                <p class="font-semibold text-brand-green" x-text="form.puesto"></p>
                <p class="text-gray-600" x-text="form.ubicacion"></p>
                <p class="text-gray-600" x-show="form.telefono" x-text="form.telefono"></p>
            </div>

            <template x-for="key in activeKeys()" :key="key">
                <div class="rounded-xl bg-brand-mist p-4">
                    <p class="font-semibold text-brand-green" x-text="labels['name_' + key]"></p>
                    <p class="text-gray-700" x-text="roleName(key)"></p>
                    <p class="text-gray-600" x-show="key === 'dashboard' && sys.dashboard.estacion_id" x-text="stationName(sys.dashboard.estacion_id) + (sys.dashboard.area ? ' · ' + areaName(sys.dashboard.area) : '')"></p>
                    <p class="text-gray-600" x-show="key === 'tickets' && sys.tickets.team_ids.length" x-text="teamNames(sys.tickets.team_ids).join(', ')"></p>
                    <p class="text-gray-600" x-show="key === 'tickets' && sys.tickets.coordination_ids.length" x-text="coordNames(sys.tickets.coordination_ids).join(', ')"></p>
                    <p class="text-gray-600" x-show="sys[key].detalle" x-text="sys[key].detalle"></p>
                </div>
            </template>

            <div class="rounded-xl bg-brand-mist p-4">
                <p class="whitespace-pre-line text-gray-700" x-text="form.funciones"></p>
                <p class="mt-2 whitespace-pre-line text-gray-600" x-show="form.justificacion" x-text="form.justificacion"></p>
            </div>

            <div class="flex items-center justify-between pt-1">
                <x-secondary-button type="button" @click="back()">{{ __('portal.access_request.back') }}</x-secondary-button>
                <x-primary-button>{{ $solicitud ? __('portal.access_request.resubmit') : __('portal.access_request.submit') }}</x-primary-button>
            </div>
        </div>
    </x-wizard.step>
</form>
