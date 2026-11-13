<x-guest-layout>
    <h1 class="text-lg font-semibold">{{ __('portal.two_factor.recovery_title') }}</h1>
    <p class="mt-2 text-sm text-gray-600">{{ __('portal.two_factor.recovery_help') }}</p>

    <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm">
        @foreach ($codes as $code)
            <li class="rounded bg-gray-100 p-2 text-center">{{ $code }}</li>
        @endforeach
    </ul>

    <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white">{{ __('portal.two_factor.continue') }}</a>
</x-guest-layout>
