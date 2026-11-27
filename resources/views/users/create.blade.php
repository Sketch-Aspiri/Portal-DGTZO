<x-app-layout>
    <x-slot name="header">
        <h1 class="font-heading font-semibold text-xl text-brand-green leading-tight">{{ __('portal.approvals.new_title') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 px-4 space-y-6">
            @if ($errors->has('business'))
                <p class="text-sm text-red-700" role="alert">{{ $errors->first('business') }}</p>
            @endif

            <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
                @csrf

                <div class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6 space-y-4">
                    <p class="text-sm text-gray-600">{{ __('portal.approvals.new_subtitle') }}</p>

                    <div>
                        <x-input-label for="name" :value="__('portal.approvals.new_name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required maxlength="255" autocomplete="off" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('portal.approvals.new_email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required maxlength="255" autocomplete="off" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="portal_role" :value="__('portal.approvals.new_portal_role')" />
                        <select id="portal_role" name="portal_role" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach (\App\Enums\PortalRole::cases() as $role)
                                @if ($esAdministrador || $role === \App\Enums\PortalRole::Usuario)
                                    <option value="{{ $role->value }}" @selected(old('portal_role', 'usuario') === $role->value)>{{ __('portal.roles.'.$role->value) }}</option>
                                @endif
                            @endforeach
                        </select>
                        @unless ($esAdministrador)
                            <p class="mt-1 text-xs text-gray-500">{{ __('portal.approvals.new_portal_role_help') }}</p>
                        @endunless
                        <x-input-error :messages="$errors->get('portal_role')" class="mt-1" />
                    </div>
                </div>

                <div class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl p-6 space-y-4">
                    <h2 class="font-semibold">{{ __('portal.approvals.grant') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('portal.approvals.grant_help') }}</p>
                    <x-input-error :messages="$errors->get('accesos')" />

                    @include('users._access-fields', ['omitir' => []])
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('portal.approvals.new_submit') }}</x-primary-button>
                    <a href="{{ route('users.index') }}" class="text-sm underline text-gray-700">{{ __('portal.approvals.back') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
