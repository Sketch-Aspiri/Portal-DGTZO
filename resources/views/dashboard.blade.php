<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-semibold text-xl text-brand-green leading-tight">{{ __('portal.launcher.title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-4">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-800 bg-green-100 rounded-lg px-3 py-2">{{ session('status') }}</p>
            @endif

            @forelse ($subsistemas as $clave => $sistema)
                @if ($loop->first)
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @endif
                <a href="{{ $sistema['url'] }}" class="block bg-white rounded-xl border border-brand-green/10 shadow-sm shadow-brand-green/5 p-6 hover:border-brand-teal/40 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
                    <span class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-brand-mist font-heading font-bold text-brand-teal" aria-hidden="true">{{ mb_strtoupper(mb_substr($sistema['nombre'], 0, 1)) }}</span>
                    <h3 class="font-heading text-lg font-semibold text-brand-green">{{ $sistema['nombre'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $sistema['descripcion'] }}</p>
                    <p class="mt-3 text-xs text-gray-500">{{ __('portal.launcher.role', ['role' => $sistema['roles'][$sistema['rol_asignado']] ?? $sistema['rol_asignado']]) }}</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-teal">{{ __('portal.launcher.open') }} →</span>
                </a>
                @if ($loop->last)
                    </div>
                @endif
            @empty
                <p class="bg-white rounded-xl border border-brand-green/10 p-6 text-gray-600">{{ __('portal.launcher.empty') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
