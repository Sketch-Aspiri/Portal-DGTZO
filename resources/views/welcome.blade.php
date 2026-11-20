<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
        <title>{{ __('portal.system_name') }}</title>
    </head>
    <body class="font-sans antialiased bg-brand-mist text-gray-900">
        <main class="min-h-screen flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-brand-green/10 bg-white shadow-lg shadow-brand-green/5 text-center">
                <div class="h-1.5 bg-brand-green"></div>
                <div class="p-8 sm:p-12">
                    <x-application-logo class="h-24 sm:h-28" />
                    <h1 class="mt-6 font-heading text-2xl sm:text-3xl font-bold leading-tight text-brand-green">{{ __('portal.welcome.title') }}</h1>
                    <p class="mt-4 text-gray-600">{{ __('portal.welcome.lead') }}</p>
                    <p class="mt-2 text-sm text-gray-500">{{ __('portal.welcome.how') }}</p>

                    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex justify-center items-center min-h-[44px] rounded-lg bg-brand-green px-5 py-3 text-sm font-semibold text-white hover:bg-brand-green-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal focus-visible:ring-offset-2">{{ __('portal.welcome.open') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex justify-center items-center min-h-[44px] rounded-lg bg-brand-green px-5 py-3 text-sm font-semibold text-white hover:bg-brand-green-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal focus-visible:ring-offset-2">{{ __('portal.welcome.login') }}</a>
                            <a href="{{ route('register') }}" class="inline-flex justify-center items-center min-h-[44px] rounded-lg border border-brand-teal/60 px-5 py-3 text-sm font-semibold text-brand-teal hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal focus-visible:ring-offset-2">{{ __('portal.welcome.register') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
