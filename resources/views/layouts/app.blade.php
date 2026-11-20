<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased bg-brand-mist text-gray-900">
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white border-b border-brand-green/10">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 font-heading text-brand-green">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="py-6 text-center text-xs text-gray-500">
                {{ __('portal.system_name') }}
            </footer>
        </div>
    </body>
</html>
