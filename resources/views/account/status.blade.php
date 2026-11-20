@if ($status === \App\Enums\UserStatus::Pending && $wizard)
    <x-guest-layout wide>
        <x-slot name="title">{{ __('portal.access_request.title') }}</x-slot>
        <x-slot name="subtitle">{{ __('portal.access_request.subtitle') }}</x-slot>

        @include('account.access-wizard')
    </x-guest-layout>
@else
    <x-guest-layout>
        @if (session('status'))
            <p class="mb-4 rounded-lg bg-green-100 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        @if ($status === \App\Enums\UserStatus::Pending)
            <h1 class="font-heading text-lg font-semibold text-brand-green">
                {{ $solicitud ? __('portal.access_request.review_status_title') : __('portal.account.pending_title') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600">
                {{ $solicitud ? __('portal.access_request.review_status_text') : __('portal.account.pending_text') }}
            </p>

            @if ($solicitud)
                <div class="mt-4 rounded-xl bg-brand-mist p-4 text-sm">
                    <p class="font-semibold text-brand-green">{{ $solicitud->puesto }}</p>
                    <p class="text-gray-600">{{ $solicitud->ubicacion }}</p>
                    <p class="mt-2 font-medium text-brand-green">{{ __('portal.access_request.requested_systems') }}</p>
                    <ul class="list-disc ps-5 text-gray-700">
                        @foreach ($solicitud->accesos as $clave => $acceso)
                            <li>{{ \App\Support\Subsistemas::get($clave)['nombre'] ?? $clave }} — {{ \App\Support\Subsistemas::get($clave)['roles'][$acceso['rol'] ?? ''] ?? ($acceso['rol'] ?? '') }}</li>
                        @endforeach
                    </ul>
                </div>

                <a href="{{ route('account.status', ['editar' => 1]) }}"
                   class="mt-4 inline-flex min-h-[44px] items-center rounded-lg border border-brand-teal/60 px-4 text-sm font-semibold text-brand-teal hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
                    {{ __('portal.access_request.edit_request') }}
                </a>
            @endif
        @else
            <h1 class="font-heading text-lg font-semibold text-brand-green">{{ __('portal.account.inactive_title') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('portal.account.inactive_text') }}</p>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <x-secondary-button type="submit">{{ __('Log Out') }}</x-secondary-button>
        </form>
    </x-guest-layout>
@endif
