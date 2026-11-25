<x-app-layout>
    @php
        $accesos = $user->subsistemasAccesibles();
        $card = 'rounded-2xl border border-brand-green/10 bg-white p-6 shadow-lg shadow-brand-green/5 sm:p-8';
    @endphp

    {{-- Banda con la foto y los datos básicos --}}
    <section class="relative isolate overflow-hidden text-white">
        <img src="{{ asset('images/tren-maya.jpeg') }}" alt="" aria-hidden="true"
             class="absolute inset-0 -z-20 h-full w-full scale-[1.3] origin-[10%_70%] object-cover blur-[2px]">
        <div class="absolute inset-0 -z-[15] bg-gradient-to-r from-brand-green-dark/95 via-brand-green/85 to-brand-teal/60"></div>
        <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD" aria-hidden="true"
                class="absolute inset-0 -z-10 h-full w-full opacity-30"></canvas>

        <div class="mx-auto flex max-w-5xl flex-col items-center gap-6 px-4 pb-24 pt-10 text-center sm:flex-row sm:px-6 sm:text-left lg:px-8">
            <div class="rise relative">
                <x-user-avatar :user="$user" size="xl" class="ring-4 ring-white/80 shadow-2xl shadow-black/30" />
            </div>
            <div class="rise" style="animation-delay:.08s">
                <p class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-mint">{{ __('portal.profile') }}</p>
                <h1 class="mt-1 font-heading text-3xl font-bold sm:text-4xl">{{ $user->name }}</h1>
                <p class="mt-1 text-white/85">{{ $user->email }}</p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-mint"></span>{{ __('portal.profile_page.one_profile') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
                        {{ trans_choice('portal.profile_page.systems_count', count($accesos), ['count' => count($accesos)]) }}
                    </span>
                </div>
            </div>
        </div>
    </section>

    <div class="relative z-10 mx-auto -mt-14 w-full max-w-5xl space-y-6 px-4 pb-16 sm:px-6 lg:px-8">
        @if (session('status') === 'avatar-updated' || session('status') === 'avatar-removed')
            <p class="rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800" role="status">
                {{ __('portal.profile_page.'.session('status')) }}
            </p>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_1.3fr]">
            {{-- Foto --}}
            <section class="{{ $card }}" x-data="{ preview: null, name: '' }">
                <h2 class="font-heading text-lg font-semibold text-brand-green">{{ __('portal.profile_page.photo_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('portal.profile_page.photo_help') }}</p>

                <div class="mt-5 flex items-center gap-5">
                    <div class="relative">
                        <template x-if="preview"><img :src="preview" alt="" class="h-24 w-24 rounded-full object-cover ring-2 ring-brand-mint"></template>
                        <div x-show="!preview"><x-user-avatar :user="$user" size="xl" class="!h-24 !w-24 !text-3xl" /></div>
                    </div>
                    <p class="text-xs text-gray-500" x-text="name || '{{ __('portal.profile_page.photo_formats') }}'"></p>
                </div>

                <x-input-error class="mt-3" :messages="array_merge($errors->get('avatar'), $errors->get('business'))" />

                <form method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                    @csrf
                    <label class="flex min-h-[44px] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-green/30 bg-brand-mist px-4 text-sm font-semibold text-brand-teal transition hover:border-brand-teal hover:bg-white focus-within:ring-2 focus-within:ring-brand-teal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="13" r="3.5"/></svg>
                        {{ __('portal.profile_page.choose_photo') }}
                        <input type="file" name="avatar" accept="image/png,image/jpeg" class="sr-only" required
                               x-on:change="const f = $event.target.files[0]; if (f) { name = f.name; preview = URL.createObjectURL(f); }">
                    </label>
                    <x-primary-button x-bind:disabled="!preview" class="w-full justify-center disabled:opacity-50">{{ __('portal.profile_page.save_photo') }}</x-primary-button>
                </form>

                @if ($user->avatar_path)
                    <form method="post" action="{{ route('profile.avatar.destroy') }}" class="mt-3">
                        @csrf
                        @method('delete')
                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">{{ __('portal.profile_page.remove_photo') }}</button>
                    </form>
                @endif
            </section>

            {{-- Datos --}}
            <div class="space-y-6">
                <section class="{{ $card }}">
                    @include('profile.partials.update-profile-information-form')
                </section>

                {{-- Sistemas con este perfil --}}
                <section class="{{ $card }}">
                    <h2 class="font-heading text-lg font-semibold text-brand-green">{{ __('portal.profile_page.systems_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('portal.profile_page.systems_help') }}</p>
                    <ul class="mt-4 divide-y divide-brand-green/10">
                        @forelse ($accesos as $sistema)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="min-w-0">
                                    <span class="block font-heading text-sm font-semibold text-brand-green">{{ $sistema['nombre'] }}</span>
                                    <span class="block text-xs text-gray-500">{{ __('portal.launcher.role', ['role' => $sistema['roles'][$sistema['rol_asignado']] ?? $sistema['rol_asignado']]) }}</span>
                                </span>
                                <a href="{{ $sistema['url'] }}" class="shrink-0 text-sm font-semibold text-brand-teal hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">{{ __('portal.launcher.open') }} →</a>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-gray-500">{{ __('portal.launcher.empty') }}</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>

        {{-- Seguridad --}}
        <div class="grid gap-6 lg:grid-cols-[1fr_1.3fr]">
            <section class="{{ $card }}">
                <h2 class="font-heading text-lg font-semibold text-brand-green">{{ __('portal.profile_page.two_factor_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('portal.profile_page.two_factor_help') }}</p>
                <p class="mt-4 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $user->hasTwoFactorEnabled() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $user->hasTwoFactorEnabled() ? 'bg-green-600' : 'bg-amber-500' }}"></span>
                    {{ $user->hasTwoFactorEnabled() ? __('portal.profile_page.two_factor_on') : __('portal.profile_page.two_factor_off') }}
                </p>
                @unless ($user->hasTwoFactorEnabled())
                    <a href="{{ route('two-factor.setup') }}" class="mt-4 block text-sm font-semibold text-brand-teal hover:underline">{{ __('portal.profile_page.two_factor_setup') }} →</a>
                @endunless
            </section>

            <section class="{{ $card }}">
                @include('profile.partials.update-password-form')
            </section>
        </div>
    </div>
</x-app-layout>
