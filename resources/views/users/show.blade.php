<x-app-layout>
    <x-slot name="header">
        <h1 class="font-heading font-semibold text-xl text-brand-green leading-tight">{{ $user->name }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 px-4 space-y-6">
            @if (session('status'))
                <p data-aviso class="text-sm text-green-800 bg-green-100 rounded-lg px-3 py-2">{{ session('status') }}</p>
            @endif
            @if ($errors->has('business'))
                <p class="text-sm text-red-700">{{ $errors->first('business') }}</p>
            @endif

            <div class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-600">{{ $user->email }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-green-100 text-green-800' => $user->status === \App\Enums\UserStatus::Active,
                                'bg-amber-100 text-amber-800' => $user->status === \App\Enums\UserStatus::Pending,
                                'bg-gray-200 text-gray-700' => $user->status === \App\Enums\UserStatus::Inactive,
                            ])>{{ $user->status->label() }}</span>
                            @if ($user->portal_role !== \App\Enums\PortalRole::Usuario)
                                <span class="rounded-full bg-brand-mist px-2 py-0.5 text-xs font-semibold text-brand-green">{{ __('portal.roles.'.$user->portal_role->value) }}</span>
                            @endif
                        </p>
                    </div>

                    @if ($puedeGestionar)
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($user->status === \App\Enums\UserStatus::Active)
                                <form method="POST" action="{{ route('users.invite', $user) }}">
                                    @csrf
                                    <button type="submit" class="min-h-[40px] rounded-lg border border-brand-green/20 px-3 text-sm font-semibold text-brand-green hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">{{ __('portal.approvals.send_password_link') }}</button>
                                </form>
                                <details class="relative">
                                    <summary class="inline-flex min-h-[40px] cursor-pointer list-none items-center rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400">{{ __('portal.approvals.deactivate') }}</summary>
                                    <form method="POST" action="{{ route('users.deactivate', $user) }}" class="absolute right-0 z-20 mt-2 w-72 space-y-3 rounded-xl border border-red-200 bg-white p-4 shadow-lg">
                                        @csrf
                                        <p class="text-sm text-gray-700">{{ __('portal.approvals.deactivate_confirm') }}</p>
                                        <x-danger-button>{{ __('portal.approvals.deactivate_yes') }}</x-danger-button>
                                    </form>
                                </details>
                            @elseif ($puedeReactivar)
                                <form method="POST" action="{{ route('users.reactivate', $user) }}">
                                    @csrf
                                    <x-primary-button>{{ __('portal.approvals.reactivate') }}</x-primary-button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($puedeGestionar && $user->status === \App\Enums\UserStatus::Active)
                    <p class="mt-3 text-xs text-gray-500">{{ __('portal.approvals.deactivate_help') }}</p>
                @endif
            </div>

            @if ($solicitud)
                <div class="bg-white border border-brand-teal/30 shadow-sm shadow-brand-green/5 rounded-xl p-6">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-heading font-semibold text-brand-green">{{ __('portal.access_request.applicant_card') }}</h3>
                        <span class="rounded-full bg-brand-mist px-2.5 py-0.5 text-xs font-semibold text-brand-green">{{ __('portal.access_request.states.'.$solicitud->estado) }}</span>
                    </div>
                    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">{{ __('portal.access_request.puesto') }}</dt><dd class="font-medium">{{ $solicitud->puesto }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('portal.access_request.ubicacion') }}</dt><dd class="font-medium">{{ $solicitud->ubicacion }}</dd></div>
                        @if ($solicitud->telefono)
                            <div><dt class="text-gray-500">{{ __('portal.access_request.telefono') }}</dt><dd class="font-medium">{{ $solicitud->telefono }}</dd></div>
                        @endif
                        <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('portal.access_request.functions') }}</dt><dd class="whitespace-pre-line">{{ $solicitud->funciones }}</dd></div>
                        @if ($solicitud->justificacion)
                            <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('portal.access_request.justification') }}</dt><dd class="whitespace-pre-line">{{ $solicitud->justificacion }}</dd></div>
                        @endif
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">{{ __('portal.access_request.requested_systems') }}</dt>
                            @foreach ($solicitud->accesos as $clave => $acceso)
                                <dd>
                                    <strong>{{ $catalogo[$clave]['nombre'] ?? $clave }}</strong>
                                    — {{ $catalogo[$clave]['roles'][$acceso['rol'] ?? ''] ?? ($acceso['rol'] ?? '') }}
                                    @if (! empty($acceso['detalle']))
                                        <span class="text-gray-600">({{ __('portal.access_request.detail_label') }}: {{ $acceso['detalle'] }})</span>
                                    @endif
                                </dd>
                            @endforeach
                        </div>
                    </dl>
                    @if ($user->status === \App\Enums\UserStatus::Pending)
                        <p class="mt-3 text-xs text-gray-500">{{ __('portal.access_request.applicant_hint') }}</p>
                    @endif
                </div>
            @endif

            @if ($user->accesos->isNotEmpty())
                <div class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6">
                    <h3 class="font-semibold">{{ __('portal.approvals.accesses') }}</h3>
                    <ul class="mt-3 divide-y text-sm">
                        @foreach ($user->accesos as $acceso)
                            @php($sistema = $catalogo[$acceso->subsistema] ?? null)
                            <li class="py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span>
                                        <strong>{{ $sistema['nombre'] ?? $acceso->subsistema }}</strong>
                                        — {{ $sistema['roles'][$acceso->rol] ?? $acceso->rol }}
                                    </span>
                                    <span class="flex items-center gap-3">
                                        <span @class([
                                            'text-green-700' => $acceso->aprovisionamiento === 'ok',
                                            'text-amber-700' => in_array($acceso->aprovisionamiento, ['pendiente', 'conflicto'], true),
                                            'text-red-700' => $acceso->aprovisionamiento === 'error',
                                        ])>
                                            {{ __('portal.provision.state.'.$acceso->aprovisionamiento) }}@if ($acceso->accion_pendiente && $acceso->aprovisionamiento !== 'ok') · {{ __('portal.provision.action.'.$acceso->accion_pendiente) }}@endif
                                        </span>
                                        @if ($acceso->aprovisionamiento === 'error')
                                            <form method="POST" action="{{ route('users.retry', [$user, $acceso->subsistema]) }}">
                                                @csrf
                                                <button type="submit" class="underline">{{ __('portal.approvals.retry') }}</button>
                                            </form>
                                        @endif
                                        @if ($puedeEditar)
                                            <details class="relative">
                                                <summary class="cursor-pointer list-none font-semibold text-red-700 underline">{{ __('portal.approvals.revoke') }}</summary>
                                                <form method="POST" action="{{ route('users.access.revoke', [$user, $acceso->subsistema]) }}" class="absolute right-0 z-20 mt-2 w-64 space-y-3 rounded-xl border border-red-200 bg-white p-4 shadow-lg">
                                                    @csrf
                                                    @method('DELETE')
                                                    <p class="text-sm text-gray-700">{{ __('portal.approvals.revoke_confirm', ['sistema' => $sistema['nombre'] ?? $acceso->subsistema]) }}</p>
                                                    <x-danger-button>{{ __('portal.approvals.revoke_yes') }}</x-danger-button>
                                                </form>
                                            </details>
                                        @endif
                                    </span>
                                </div>
                                @if ($acceso->ultimo_error)
                                    <p class="mt-1 text-xs text-gray-500">{{ $acceso->ultimo_error }}</p>
                                @endif

                                @if ($puedeEditar)
                                    <details class="mt-2" @if ($errors->has('accesos') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'accesos.'.$acceso->subsistema.'.'))) open @endif>
                                        <summary class="cursor-pointer text-sm font-semibold text-brand-teal">{{ __('portal.approvals.edit_access') }}</summary>
                                        <form method="POST" action="{{ route('users.access.update', [$user, $acceso->subsistema]) }}" class="mt-3 space-y-3">
                                            @csrf
                                            @method('PUT')
                                            @include('users._access-fields', [
                                                'omitir' => array_values(array_diff(array_keys($catalogo), [$acceso->subsistema])),
                                                'fijo' => true,
                                                'prefill' => [$acceso->subsistema => ['rol' => $acceso->rol] + (array) $acceso->contexto],
                                            ])
                                            <x-primary-button>{{ __('portal.approvals.save_changes') }}</x-primary-button>
                                        </form>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($user->status === \App\Enums\UserStatus::Pending)
                <form method="POST" action="{{ route('users.approve', $user) }}" class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6 space-y-4">
                    @csrf
                    <h3 class="font-semibold">{{ __('portal.approvals.grant') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('portal.approvals.grant_help') }}</p>
                    <x-input-error :messages="$errors->get('accesos')" />

                    @include('users._access-fields', ['omitir' => [], 'solicitud' => $solicitud])

                    <x-primary-button>{{ __('portal.approvals.approve') }}</x-primary-button>
                </form>

                <form method="POST" action="{{ route('users.reject', $user) }}" class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6 space-y-3">
                    @csrf
                    <x-input-label for="reason" :value="__('portal.approvals.reason')" />
                    <textarea id="reason" name="reason" rows="2" required maxlength="500" class="block w-full rounded-md border-gray-300 text-sm">{{ old('reason') }}</textarea>
                    <x-input-error :messages="$errors->get('reason')" />
                    <x-danger-button>{{ __('portal.approvals.reject') }}</x-danger-button>
                </form>
            @elseif ($user->status === \App\Enums\UserStatus::Active && $puedeDarAcceso)
                @if ($faltantes === [])
                    <p class="text-sm text-gray-600">{{ __('portal.approvals.has_all') }}</p>
                @else
                    <form method="POST" action="{{ route('users.grant', $user) }}" class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6 space-y-4">
                        @csrf
                        <h3 class="font-semibold">{{ __('portal.approvals.add_access') }}</h3>
                        <p class="text-sm text-gray-600">{{ __('portal.approvals.add_access_help') }}</p>
                        <x-input-error :messages="$errors->get('accesos')" />

                        @include('users._access-fields', ['omitir' => array_values(array_diff(array_keys($catalogo), $faltantes))])

                        <x-primary-button>{{ __('portal.approvals.add_access_button') }}</x-primary-button>
                    </form>
                @endif
            @endif

            <a href="{{ route('users.index') }}" class="text-sm underline text-gray-700">{{ __('portal.approvals.back') }}</a>
        </div>
    </div>
</x-app-layout>
