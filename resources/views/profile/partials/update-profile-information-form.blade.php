<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('portal.profile_info') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('portal.profile_info_help') }}</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('portal.name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('portal.email')" />
            <x-text-input id="email" type="email" class="mt-1 block w-full bg-gray-100" :value="$user->email" disabled />
            <p class="mt-1 text-xs text-gray-500">{{ __('portal.email_locked') }}</p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('portal.save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p data-aviso class="text-sm text-gray-600">{{ __('portal.saved') }}</p>
            @endif
        </div>
    </form>
</section>
