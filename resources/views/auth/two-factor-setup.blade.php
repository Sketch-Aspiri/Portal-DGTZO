<x-guest-layout>
    <h1 class="text-lg font-semibold">{{ __('portal.two_factor.setup_title') }}</h1>
    <p class="mt-2 text-sm text-gray-600">{{ __('portal.two_factor.setup_help') }}</p>

    <div class="mt-4 flex justify-center" role="img" aria-label="{{ __('portal.two_factor.qr_label') }}">{!! $qr_svg !!}</div>

    <p class="mt-4 text-sm text-gray-600">{{ __('portal.two_factor.manual_key') }}</p>
    <p class="mt-1 break-all rounded bg-gray-100 p-2 font-mono text-sm">{{ $secret }}</p>

    <form method="POST" action="{{ route('two-factor.setup') }}" class="mt-6">
        @csrf
        <x-input-label for="code" :value="__('portal.two_factor.code')" />
        <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-full" required autofocus />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />

        <div class="mt-4 flex items-center justify-between">
            <x-primary-button>{{ __('portal.two_factor.confirm') }}</x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm text-gray-600 underline">{{ __('Log Out') }}</button>
    </form>
</x-guest-layout>
