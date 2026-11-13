<x-guest-layout>
    <h1 class="text-lg font-semibold">{{ __('portal.two_factor.challenge_title') }}</h1>
    <p class="mt-2 text-sm text-gray-600">{{ __('portal.two_factor.challenge_help') }}</p>

    <form method="POST" action="{{ route('two-factor.challenge') }}" class="mt-4">
        @csrf
        <x-input-label for="code" :value="__('portal.two_factor.code')" />
        <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-full" autofocus />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />

        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-gray-600">{{ __('portal.two_factor.use_recovery') }}</summary>
            <x-text-input id="recovery_code" name="recovery_code" type="text" autocomplete="off" class="mt-2 block w-full" />
        </details>

        <div class="mt-4"><x-primary-button>{{ __('portal.two_factor.verify') }}</x-primary-button></div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm text-gray-600 underline">{{ __('Log Out') }}</button>
    </form>
</x-guest-layout>
