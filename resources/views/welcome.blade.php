<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => __('portal.system_name')])
    </head>
    <body class="flex min-h-screen flex-col bg-brand-mist font-sans text-gray-900 antialiased">
        @php
            $btnPrimary = 'inline-flex min-h-[48px] items-center justify-center rounded-xl bg-white px-6 text-sm font-semibold text-brand-green shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green';
            $btnGhost = 'inline-flex min-h-[48px] items-center justify-center rounded-xl border border-white/40 bg-white/5 px-6 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white';
        @endphp

        <x-saltar-contenido />

        {{-- Hero --}}
        <section class="relative isolate overflow-hidden text-white">
            <img src="{{ asset('images/tren-maya.jpeg') }}" alt="" aria-hidden="true"
                 class="absolute inset-0 -z-20 h-full w-full scale-[1.3] origin-[10%_70%] object-cover blur-[2px]">
            <div class="absolute inset-0 -z-[15] bg-gradient-to-br from-brand-green-dark/95 via-brand-green/75 to-brand-teal/30"></div>
            <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD" aria-hidden="true"
                    class="absolute inset-0 -z-10 h-full w-full opacity-40 [mask-image:linear-gradient(to_right,black_0%,black_40%,transparent_95%)]"></canvas>
            <div class="absolute -right-24 top-1/3 -z-10 h-96 w-96 rounded-full bg-brand-mint/20 blur-3xl" aria-hidden="true"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-40 bg-gradient-to-t from-brand-green-dark/50 to-transparent" aria-hidden="true"></div>

            <header class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
                <a href="/" aria-label="Tren Maya">
                    <x-application-logo class="h-12 sm:h-14 [&_img]:brightness-0 [&_img]:invert" />
                </a>
                <nav class="flex items-center gap-2 sm:gap-3">
                    <x-panel-accesibilidad class="rounded-md bg-white" />
                    <a href="{{ route('tutorials.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">{{ __('portal.tutorials.help') }}</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="{{ $btnPrimary }} !min-h-[44px] !rounded-lg !px-4">{{ __('portal.welcome.open') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">{{ __('portal.welcome.login') }}</a>
                        <a href="{{ route('register') }}" class="{{ $btnPrimary }} !min-h-[44px] !rounded-lg !px-4">{{ __('portal.welcome.register') }}</a>
                    @endauth
                </nav>
            </header>

            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pb-48 pt-12 sm:px-6 sm:pt-20 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div>
                    <p class="rise inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.15em] text-brand-mint backdrop-blur sm:text-[0.8rem]">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-mint"></span>{{ __('portal.welcome.eyebrow') }}
                    </p>
                    <h1 class="rise mt-6 font-heading text-4xl font-bold leading-[1.1] tracking-tight sm:text-6xl" style="animation-delay:.08s">{{ __('portal.welcome.title') }}</h1>
                    <p class="rise mt-6 max-w-xl text-lg leading-relaxed text-white/90" style="animation-delay:.16s">{{ __('portal.welcome.lead') }}</p>

                    <div class="rise mt-9 flex flex-col gap-3 sm:flex-row" style="animation-delay:.24s">
                        @auth
                            <a href="{{ route('dashboard') }}" class="{{ $btnPrimary }}">{{ __('portal.welcome.open') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="{{ $btnPrimary }}">{{ __('portal.welcome.login') }}</a>
                            <a href="{{ route('register') }}" class="{{ $btnGhost }}">{{ __('portal.welcome.register') }}</a>
                        @endauth
                    </div>

                    <ul class="rise mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/85" style="animation-delay:.32s">
                        @foreach (['chip1', 'chip2', 'chip3'] as $chip)
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-brand-mint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                {{ __('portal.welcome.'.$chip) }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Vista previa del lanzador de sistemas --}}
                <div class="rise floaty hidden lg:block" style="animation-delay:.3s" aria-hidden="true">
                    <div class="rounded-2xl border border-white/25 bg-white/10 p-5 shadow-2xl shadow-black/30 backdrop-blur-xl">
                        <div class="flex items-center justify-between">
                            <span class="font-heading text-sm font-semibold">{{ __('portal.welcome.preview_title') }}</span>
                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-[0.65rem] uppercase tracking-wider text-white/80">{{ __('portal.welcome.preview_hint') }}</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-3 rounded-xl bg-white p-3.5 text-gray-900 shadow-lg">
                                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-green text-white">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-heading text-sm font-semibold text-brand-green">{{ __('portal.welcome.sys_dashboard') }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ __('portal.welcome.sys_dashboard_text') }}</span>
                                </span>
                                <span class="rounded-md bg-brand-mist px-2 py-1 text-xs font-semibold text-brand-teal">{{ __('portal.launcher.open') }}</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl bg-white p-3.5 text-gray-900 shadow-lg">
                                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-teal text-white">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 012-2h14a2 2 0 012 2v1.5a1.5 1.5 0 000 3V15a2 2 0 01-2 2H5a2 2 0 01-2-2v-1.5a1.5 1.5 0 000-3V9z"/><path d="M14 7v10" stroke-dasharray="2 2"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-heading text-sm font-semibold text-brand-green">{{ __('portal.welcome.sys_tickets') }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ __('portal.welcome.sys_tickets_text') }}</span>
                                </span>
                                <span class="rounded-md bg-brand-mist px-2 py-1 text-xs font-semibold text-brand-teal">{{ __('portal.launcher.open') }}</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl bg-white p-3.5 text-gray-900 shadow-lg">
                                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-green-dark text-white">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-heading text-sm font-semibold text-brand-green">{{ __('portal.welcome.sys_calendar') }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ __('portal.welcome.sys_calendar_text') }}</span>
                                </span>
                                <span class="rounded-md bg-brand-mist px-2 py-1 text-xs font-semibold text-brand-teal">{{ __('portal.launcher.open') }}</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl border border-dashed border-white/40 p-3.5">
                                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-white/15 text-white">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-heading text-sm font-semibold">{{ __('portal.welcome.sys_more') }}</span>
                                    <span class="block truncate text-xs text-white/75">{{ __('portal.welcome.sys_more_text') }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <main id="contenido" tabindex="-1" class="relative z-10 mx-auto -mt-24 w-full max-w-6xl flex-1 px-4 pb-8 sm:px-6 lg:px-8">
            {{-- Cifras --}}
            <div class="reveal grid grid-cols-1 divide-y divide-brand-green/10 rounded-2xl border border-brand-green/10 bg-white shadow-xl shadow-brand-green/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                @foreach ([1, 2, 3] as $n)
                    <div class="px-6 py-6 text-center">
                        <p class="font-heading text-3xl font-bold text-brand-green">{{ __('portal.welcome.stat'.$n.'_value') }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ __('portal.welcome.stat'.$n.'_label') }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Pasos --}}
            <section class="mt-20" aria-labelledby="como-funciona">
                <h2 id="como-funciona" class="reveal text-center font-heading text-2xl font-bold text-brand-green sm:text-3xl">{{ __('portal.welcome.steps_title') }}</h2>
                <ol class="relative mt-10 grid gap-5 md:grid-cols-3">
                    <div class="absolute left-[16%] right-[16%] top-[2.1rem] hidden border-t-2 border-dashed border-brand-mint md:block" aria-hidden="true"></div>
                    @foreach ([1, 2, 3] as $n)
                        <li class="reveal relative rounded-2xl border border-brand-green/10 bg-white p-6 shadow-md shadow-brand-green/5 transition hover:-translate-y-1 hover:shadow-xl" style="transition-delay: {{ ($n - 1) * 90 }}ms">
                            <span class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-brand-teal to-brand-green font-heading text-lg font-bold text-white shadow-lg shadow-brand-green/30" aria-hidden="true">{{ $n }}</span>
                            <h3 class="mt-5 font-heading text-lg font-semibold text-brand-green">{{ __('portal.welcome.step'.$n.'_title') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('portal.welcome.step'.$n.'_text') }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Características --}}
            <section class="mt-24" aria-labelledby="caracteristicas">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <h2 id="caracteristicas" class="font-heading text-2xl font-bold text-brand-green sm:text-3xl">{{ __('portal.welcome.features_title') }}</h2>
                    <p class="mt-3 text-gray-600">{{ __('portal.welcome.features_lead') }}</p>
                </div>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    @php
                        $icons = [
                            1 => '<path d="M15 7a4 4 0 11-8 0 4 4 0 018 0zM3 20a7 7 0 0114 0M17 11l2 2 3-3"/>',
                            2 => '<path d="M12 21s7-5.2 7-11a7 7 0 10-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
                            3 => '<path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
                        ];
                    @endphp
                    @foreach ([1, 2, 3] as $n)
                        <div class="reveal group rounded-2xl border border-brand-green/10 bg-white p-6 transition hover:border-brand-mint hover:shadow-lg" style="transition-delay: {{ ($n - 1) * 90 }}ms">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-mist text-brand-teal transition group-hover:bg-brand-green group-hover:text-white">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$n] !!}</svg>
                            </span>
                            <h3 class="mt-5 font-heading text-lg font-semibold text-brand-green">{{ __('portal.welcome.f'.$n.'_title') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('portal.welcome.f'.$n.'_text') }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Llamado final --}}
            <section class="reveal relative isolate mt-24 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-green-dark via-brand-green to-brand-teal px-6 py-14 text-center text-white shadow-xl shadow-brand-green/20 sm:px-12">
                <div class="absolute -left-10 -top-10 -z-10 h-56 w-56 rounded-full bg-brand-mint/20 blur-3xl" aria-hidden="true"></div>
                <div class="absolute -bottom-16 -right-10 -z-10 h-64 w-64 rounded-full bg-brand-mint/15 blur-3xl" aria-hidden="true"></div>
                <h2 class="font-heading text-2xl font-bold sm:text-4xl">{{ __('portal.welcome.cta_title') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-white/90">{{ __('portal.welcome.cta_text') }}</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ route('dashboard') }}" class="{{ $btnPrimary }}">{{ __('portal.welcome.open') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="{{ $btnPrimary }}">{{ __('portal.welcome.register') }}</a>
                        <a href="{{ route('login') }}" class="{{ $btnGhost }}">{{ __('portal.welcome.login') }}</a>
                    @endauth
                </div>
            </section>
        </main>

        <footer class="mt-12 bg-brand-green-dark py-8 text-center text-xs text-white/85">
            <div class="mb-3 flex justify-center"><x-application-logo class="h-10 opacity-90 [&_img]:brightness-0 [&_img]:invert" /></div>
            {{ __('portal.welcome.footer') }}
        </footer>

        <x-pantalla-carga />
        <x-accesibilidad-base />
    </body>
</html>
