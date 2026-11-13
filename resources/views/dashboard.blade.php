<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('portal.launcher.title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-4">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-700">{{ session('status') }}</p>
            @endif

            @forelse ($subsistemas as $clave => $sistema)
                @if ($loop->first)
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @endif
                <a href="{{ $sistema['url'] }}" class="block bg-white shadow rounded-xl p-6 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-gray-500">
                    <h3 class="text-lg font-semibold">{{ $sistema['nombre'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $sistema['descripcion'] }}</p>
                    <p class="mt-3 text-xs text-gray-500">{{ __('portal.launcher.role', ['role' => $sistema['roles'][$sistema['rol_asignado']] ?? $sistema['rol_asignado']]) }}</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-gray-800">{{ __('portal.launcher.open') }} →</span>
                </a>
                @if ($loop->last)
                    </div>
                @endif
            @empty
                <p class="bg-white shadow rounded-xl p-6 text-gray-600">{{ __('portal.launcher.empty') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
