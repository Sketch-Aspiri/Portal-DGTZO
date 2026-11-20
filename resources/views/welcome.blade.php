<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
        <title>{{ __('portal.system_name') }}</title>
    </head>
    <body class="flex min-h-screen flex-col font-sans antialiased bg-brand-mist text-gray-900">
        {{-- Hero: foto del tren difuminada con capa verde --}}
        <section class="relative isolate overflow-hidden text-white">
            <img src="{{ asset('images/tren-maya.jpeg') }}" alt="" aria-hidden="true"
                 class="absolute inset-0 -z-20 h-full w-full scale-[1.3] origin-[10%_70%] object-cover blur-[2px]">
            <div class="absolute inset-0 -z-[15] bg-gradient-to-r from-brand-green-dark/95 via-brand-green/60 to-brand-green/10"></div>
            <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD" aria-hidden="true"
                    class="absolute inset-0 -z-10 h-full w-full opacity-50 [mask-image:linear-gradient(to_right,black_0%,black_30%,transparent_80%)]"></canvas>
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-green-dark/30 via-transparent to-brand-green-dark/40"></div>

            <header class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
                <a href="/" aria-label="Tren Maya">
                    <x-application-logo class="h-12 sm:h-14 [&_img]:brightness-0 [&_img]:invert" />
                </a>
                <nav class="flex items-center gap-2 sm:gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-white px-4 text-sm font-semibold text-brand-green hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green">{{ __('portal.welcome.open') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">{{ __('portal.welcome.login') }}</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-white px-4 text-sm font-semibold text-brand-green hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green">{{ __('portal.welcome.register') }}</a>
                    @endauth
                </nav>
            </header>

            <div class="mx-auto max-w-6xl px-4 pb-44 pt-16 sm:px-6 sm:pt-24 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-mint sm:text-sm">{{ __('portal.welcome.eyebrow') }}</p>
                <h1 class="mt-4 max-w-3xl font-heading text-3xl font-bold leading-tight sm:text-5xl">{{ __('portal.welcome.title') }}</h1>
                <p class="mt-5 max-w-2xl text-lg text-white/90">{{ __('portal.welcome.lead') }}</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-lg bg-white px-6 text-sm font-semibold text-brand-green hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green">{{ __('portal.welcome.open') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-lg bg-white px-6 text-sm font-semibold text-brand-green hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green">{{ __('portal.welcome.login') }}</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-white/70 px-6 text-sm font-semibold text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green">{{ __('portal.welcome.register') }}</a>
                    @endauth
                </div>
            </div>
        </section>

        {{-- Pasos, montados sobre el borde del hero --}}
        <main class="relative z-10 mx-auto -mt-28 w-full max-w-6xl flex-1 px-4 pb-16 sm:px-6 lg:px-8">
            <h2 class="sr-only">{{ __('portal.welcome.steps_title') }}</h2>
            <ol class="grid gap-4 md:grid-cols-3">
                @foreach ([1, 2, 3] as $n)
                    <li class="rounded-xl border border-brand-green/10 bg-white p-6 shadow-lg shadow-brand-green/10">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-mist font-heading text-lg font-bold text-brand-teal" aria-hidden="true">{{ $n }}</span>
                        <h3 class="mt-4 font-heading text-lg font-semibold text-brand-green">{{ __('portal.welcome.step'.$n.'_title') }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('portal.welcome.step'.$n.'_text') }}</p>
                    </li>
                @endforeach
            </ol>
        </main>

        <footer class="bg-brand-green py-6 text-center text-xs text-white/90">
            {{ __('portal.welcome.footer') }}
        </footer>
    </body>
</html>
