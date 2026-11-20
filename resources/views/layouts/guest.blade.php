<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans text-gray-900 antialiased bg-brand-mist">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-8">
            <a href="/" class="mb-6">
                <x-application-logo class="h-20 sm:h-24" />
            </a>

            <div class="w-full sm:max-w-md overflow-hidden rounded-2xl border border-brand-green/10 bg-white shadow-lg shadow-brand-green/5">
                <div class="h-1.5 bg-brand-green"></div>
                <div class="px-6 py-6">
                    {{ $slot }}
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-gray-500">{{ __('portal.system_name') }}</p>
        </div>
    </body>
</html>
