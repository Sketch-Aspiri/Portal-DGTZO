{{-- Con sesión completa usa el marco normal del portal; sin ella (guías públicas), uno sencillo sin menú de cuenta. --}}
@if ($completa)
    <x-app-layout>
        @include($contenido)
    </x-app-layout>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            @include('layouts.partials.head')
        </head>
        <body class="flex min-h-screen flex-col bg-brand-mist font-sans text-gray-900 antialiased">
            <header class="border-b border-brand-green/10 bg-white">
                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="logo-placa"><x-application-logo class="h-10" /></a>
                    <div class="flex items-center gap-2">
                        <x-selector-tema />
                        @guest
                            <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-dark">{{ __('portal.welcome.login') }}</a>
                        @else
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-dark">{{ __('portal.welcome.open') }}</a>
                        @endguest
                    </div>
                </div>
            </header>
            <main class="flex-1">
                @include($contenido)
            </main>
            <footer class="bg-brand-green py-6 text-center text-xs text-white/90">{{ __('portal.system_name') }}</footer>
        </body>
    </html>
@endif
