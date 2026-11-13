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
                    @if ($user->status === \App\Enums\UserStatus::Active)
                        <p class="mt-3 text-xs text-gray-500">{{ __('portal.approvals.no_removal') }}</p>
                    @endif
                </div>
            @endif

            @if ($user->status === \App\Enums\UserStatus::Pending)
                <form method="POST" action="{{ route('users.approve', $user) }}" class="bg-white shadow rounded-xl p-6 space-y-4">
                    @csrf
                    <h3 class="font-semibold">{{ __('portal.approvals.grant') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('portal.approvals.grant_help') }}</p>
                    <x-input-error :messages="$errors->get('accesos')" />

                    @include('users._access-fields', ['omitir' => []])

                    <x-primary-button>{{ __('portal.approvals.approve') }}</x-primary-button>
                </form>

                <form method="POST" action="{{ route('users.reject', $user) }}" class="bg-white shadow rounded-xl p-6 space-y-3">
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
                    <form method="POST" action="{{ route('users.grant', $user) }}" class="bg-white shadow rounded-xl p-6 space-y-4">
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
