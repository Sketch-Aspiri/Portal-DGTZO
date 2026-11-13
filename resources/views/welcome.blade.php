<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('portal.system_name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <main class="min-h-screen flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-2xl bg-white shadow-md rounded-xl p-8 sm:p-12 text-center">
                <h1 class="text-2xl sm:text-3xl font-bold leading-tight">{{ __('portal.welcome.title') }}</h1>
                <p class="mt-4 text-gray-600">{{ __('portal.welcome.lead') }}</p>
                <p class="mt-2 text-sm text-gray-500">{{ __('portal.welcome.how') }}</p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex justify-center rounded-lg bg-gray-800 px-5 py-3 text-sm font-semibold text-white hover:bg-gray-700">{{ __('portal.welcome.open') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex justify-center rounded-lg bg-gray-800 px-5 py-3 text-sm font-semibold text-white hover:bg-gray-700">{{ __('portal.welcome.login') }}</a>
                        <a href="{{ route('register') }}" class="inline-flex justify-center rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('portal.welcome.register') }}</a>
                    @endauth
                </div>
            </div>
        </main>
    </body>
</html>
