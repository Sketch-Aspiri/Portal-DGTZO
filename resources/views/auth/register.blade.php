<x-guest-layout>
    <x-slot name="title">{{ __('portal.auth.register_title') }}</x-slot>
    <x-slot name="subtitle">{{ __('portal.auth.register_subtitle') }}</x-slot>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <div class="relative mt-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-teal" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>
                <x-text-input id="name" class="block w-full min-h-[44px] pl-10" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <div class="relative mt-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-teal" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                <x-text-input id="email" class="block w-full min-h-[44px] pl-10" type="email" name="email" :value="old('email')" required autocomplete="username" aria-describedby="email-hint" />
            </div>
            <p id="email-hint" class="mt-1 text-xs text-gray-500">{{ __('portal.auth.email_hint') }}</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input id="password" name="password" autocomplete="new-password" aria-describedby="password-hint" />
            <p id="password-hint" class="mt-1 text-xs text-gray-500">{{ __('portal.auth.password_hint') }}</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Register') }}</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        {{ __('portal.auth.have_account') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand-teal hover:text-brand-green-dark hover:underline">{{ __('portal.welcome.login') }}</a>
    </p>
</x-guest-layout>
