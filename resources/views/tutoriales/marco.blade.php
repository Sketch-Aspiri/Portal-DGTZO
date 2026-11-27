{{-- Con sesión completa usa el marco normal del portal; sin ella (guías públicas), uno sencillo sin menú de cuenta. --}}
@if ($completa)
    <x-app-layout>
        @include($contenido)
    </x-app-layout>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            @include('layouts.partials.head', ['title' => $titulo ?? (isset($guia) ? $guia->titulo : __('portal.tutorials.title'))])
        </head>
        <body class="flex min-h-screen flex-col bg-brand-mist font-sans text-gray-900 antialiased">
            <x-saltar-contenido />
            <header class="border-b border-brand-green/10 bg-white">
                <div class="mx-auto flex min-h-[4rem] max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-1 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="logo-placa"><x-application-logo class="h-10" /></a>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-selector-tema />
                        <x-panel-accesibilidad><x-selector-tema variante="segmentos" /></x-panel-accesibilidad>
                        @guest
                            <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-dark">{{ __('portal.welcome.login') }}</a>
                        @else
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-dark">{{ __('portal.welcome.open') }}</a>
                        @endguest
                    </div>
                </div>
            </header>
            <main id="contenido" tabindex="-1" class="flex-1">
                @include($contenido)
            </main>
            <footer class="bg-brand-green py-6 text-center text-xs text-white/90">
                {{ __('portal.system_name') }}
                <div class="mt-2"><x-enlace-accesibilidad /></div>
            </footer>

            <x-accesibilidad-base />
            <x-pantalla-carga />
        </body>
    </html>
@endif
