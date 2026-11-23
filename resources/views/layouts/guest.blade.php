<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
        <style>[x-cloak]{display:none !important}</style>
    </head>
    <body class="font-sans text-gray-900 antialiased bg-brand-mist">
        <div class="min-h-screen lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            {{-- Marca --}}
            <x-auth-brand-panel class="hidden lg:flex lg:flex-col lg:justify-between lg:p-12">
                <a href="/" aria-label="Tren Maya">
                    <x-application-logo class="h-16 [&_img]:brightness-0 [&_img]:invert" />
                </a>

                <div>
                    <h2 class="font-heading text-3xl font-bold leading-tight">{{ __('portal.auth.panel_title') }}</h2>
                    <ul class="mt-8 space-y-4 text-white/90">
                        @foreach ([1, 2, 3] as $n)
                            <li class="flex gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-mint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>{{ __('portal.auth.point'.$n) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="text-xs text-white/70">{{ __('portal.system_name') }} · {{ __('portal.auth.secure_note') }}</p>
            </x-auth-brand-panel>

            {{-- Formulario --}}
            <div class="flex min-h-screen flex-col lg:min-h-0">
                <x-auth-brand-panel class="px-6 py-5 lg:hidden">
                    <a href="/" aria-label="Tren Maya" class="inline-block">
                        <x-application-logo class="h-12 [&_img]:brightness-0 [&_img]:invert" />
                    </a>
                </x-auth-brand-panel>

                <div class="flex justify-end px-4 pt-4 sm:px-8">
                    <x-selector-tema />
                </div>

                <main class="flex flex-1 items-center justify-center px-4 py-8 sm:px-8">
                    <div class="w-full {{ $attributes->has('wide') ? 'max-w-2xl' : 'max-w-md' }}">
                        <div class="rounded-2xl border border-brand-green/10 bg-white p-6 shadow-lg shadow-brand-green/5 sm:p-8">
                            @isset($title)
                                <h1 class="font-heading text-2xl font-bold text-brand-green">{{ $title }}</h1>
                                @isset($subtitle)
                                    <p class="mt-2 text-sm text-gray-600">{{ $subtitle }}</p>
                                @endisset
                                <div class="mt-6">{{ $slot }}</div>
                            @else
                                {{ $slot }}
                            @endisset
                        </div>
                        <p class="mt-6 text-center text-xs text-gray-500">{{ __('portal.system_name') }}</p>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
