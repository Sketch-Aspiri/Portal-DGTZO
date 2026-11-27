<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased bg-brand-mist text-gray-900">
        <x-saltar-contenido />

        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white border-b border-brand-green/10">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 font-heading text-brand-green">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main id="contenido" tabindex="-1" class="flex-1">
                {{ $slot }}
            </main>

            <footer class="bg-brand-green py-6 text-center text-xs text-white/90">
                {{ __('portal.system_name') }}
            </footer>
        </div>

        <x-accesibilidad-base />
        <x-pantalla-carga />
    </body>
</html>
