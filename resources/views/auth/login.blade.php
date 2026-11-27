<x-guest-layout>
    <x-slot name="title">{{ __('portal.auth.login_title') }}</x-slot>
    <x-slot name="subtitle">{{ __('portal.auth.login_subtitle') }}</x-slot>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <div class="relative mt-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-teal" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                <x-text-input id="email" class="block w-full min-h-[44px] pl-10" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input id="password" name="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-teal shadow-sm focus:ring-brand-teal" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-brand-teal hover:text-brand-green-dark hover:underline rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full">{{ __('Log in') }}</x-primary-button>
    </form>

    @if (Route::has('register'))
        <p class="mt-6 text-center text-sm text-gray-600">
            {{ __('portal.auth.no_account') }}
            <a href="{{ route('register') }}" class="font-semibold text-brand-teal hover:text-brand-green-dark hover:underline">{{ __('portal.welcome.register') }}</a>
        </p>
    @endif

    <p class="mt-3 text-center text-sm">
        <a href="{{ route('tutorials.index') }}" class="text-brand-teal hover:text-brand-green-dark hover:underline">{{ __('portal.tutorials.login_help') }}</a>
    </p>
</x-guest-layout>
