<x-app-layout>
    @php
        $hora = now()->hour;
        $saludo = $hora < 12 ? 'greeting_morning' : ($hora < 19 ? 'greeting_afternoon' : 'greeting_evening');
        $nombre = explode(' ', trim(auth()->user()->name))[0];
        $iconos = [
            'dashboard' => '<path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>',
            'tickets' => '<path d="M3 9a2 2 0 012-2h14a2 2 0 012 2v1.5a1.5 1.5 0 000 3V15a2 2 0 01-2 2H5a2 2 0 01-2-2v-1.5a1.5 1.5 0 000-3V9z"/><path d="M14 7v10" stroke-dasharray="2 2"/>',
        ];
    @endphp

    {{-- Banda de bienvenida --}}
    <section class="relative isolate overflow-hidden text-white">
        <img src="{{ asset('images/tren-maya.jpeg') }}" alt="" aria-hidden="true"
             class="absolute inset-0 -z-20 h-full w-full scale-[1.3] origin-[10%_70%] object-cover blur-[2px]">
        <div class="absolute inset-0 -z-[15] bg-gradient-to-r from-brand-green-dark/95 via-brand-green/85 to-brand-teal/60"></div>
        <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD" aria-hidden="true"
                class="absolute inset-0 -z-10 h-full w-full opacity-30"></canvas>
        <div class="mx-auto max-w-7xl px-4 pb-20 pt-10 sm:px-6 sm:pt-12 lg:px-8">
            <p class="rise text-sm font-semibold uppercase tracking-[0.15em] text-brand-mint">{{ __('portal.launcher.title') }}</p>
            <h1 class="rise mt-2 font-heading text-3xl font-bold sm:text-4xl" style="animation-delay:.06s">{{ __('portal.launcher.'.$saludo) }}, {{ $nombre }}</h1>
            <p class="rise mt-3 max-w-2xl text-white/90" style="animation-delay:.12s">{{ __('portal.launcher.subtitle') }}</p>
            <p class="rise mt-5 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur" style="animation-delay:.18s">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-mint"></span>{{ trans_choice('portal.launcher.count', count($subsistemas), ['count' => count($subsistemas)]) }}
            </p>
        </div>
    </section>

    <div class="relative z-10 mx-auto -mt-12 w-full max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        @if (session('status'))
            <p class="mb-4 rounded-lg bg-green-100 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        @if (! count($subsistemas))
            <div class="rise mb-5 rounded-2xl border border-dashed border-brand-green/25 bg-white p-10 text-center shadow-lg shadow-brand-green/10">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-brand-mist text-brand-teal">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 118 0v3"/></svg>
                </span>
                <p class="mt-4 text-gray-600">{{ __('portal.launcher.empty') }}</p>
            </div>
        @endif

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($subsistemas as $clave => $sistema)
                    <a href="{{ $sistema['url'] }}"
                       class="rise group relative flex flex-col overflow-hidden rounded-2xl border border-brand-green/10 bg-white p-6 shadow-lg shadow-brand-green/10 transition duration-300 hover:-translate-y-1 hover:border-brand-mint hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal"
                       style="animation-delay: {{ 0.15 + $loop->index * 0.08 }}s">
                        <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-teal to-brand-mint opacity-0 transition group-hover:opacity-100" aria-hidden="true"></span>
                        <div class="flex items-start justify-between">
                            <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-brand-teal to-brand-green text-white shadow-md shadow-brand-green/30 transition group-hover:scale-105" aria-hidden="true">
                                @if (isset($iconos[$clave]))
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $iconos[$clave] !!}</svg>
                                @else
                                    <span class="font-heading text-xl font-bold">{{ mb_strtoupper(mb_substr($sistema['nombre'], 0, 1)) }}</span>
                                @endif
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-mist px-2.5 py-1 text-xs font-semibold text-brand-teal">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('portal.launcher.available') }}
                            </span>
                        </div>
                        <h2 class="mt-5 font-heading text-xl font-semibold text-brand-green">{{ $sistema['nombre'] }}</h2>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-gray-600">{{ $sistema['descripcion'] }}</p>
                        <div class="mt-5 flex items-center justify-between border-t border-brand-green/10 pt-4">
                            <span class="text-xs text-gray-500">{{ __('portal.launcher.role', ['role' => $sistema['roles'][$sistema['rol_asignado']] ?? $sistema['rol_asignado']]) }}</span>
                            <span class="inline-flex items-center gap-1 text-sm font-semibold text-brand-teal">
                                {{ __('portal.launcher.open') }}
                                <svg class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.3 4.3a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4L13.6 11H4a1 1 0 110-2h9.6l-3.3-3.3a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
                            </span>
                        </div>
                    </a>
                @endforeach

                {{-- Temayin AI: aún sin dirección; se muestra a todas las cuentas activas como próxima herramienta. --}}
                <div class="rise relative flex flex-col overflow-hidden rounded-2xl border border-brand-green/10 bg-gradient-to-br from-brand-green-dark via-brand-green to-brand-teal p-6 text-white shadow-lg shadow-brand-green/20"
                     style="animation-delay: {{ 0.15 + count($subsistemas) * 0.08 }}s" role="group" aria-label="{{ __('portal.launcher.temayin_name') }}">
                    <span class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-brand-mint/20 blur-2xl" aria-hidden="true"></span>
                    <div class="relative flex items-start justify-between">
                        <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30 backdrop-blur" aria-hidden="true">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4L12 3z"/><path d="M18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9L18 15z"/></svg>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold ring-1 ring-white/25">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span>{{ __('portal.launcher.coming_soon') }}
                        </span>
                    </div>
                    <h2 class="relative mt-5 font-heading text-xl font-semibold">{{ __('portal.launcher.temayin_name') }}</h2>
                    <p class="relative mt-2 flex-1 text-sm leading-relaxed text-white/85">{{ __('portal.launcher.temayin_text') }}</p>
                    <div class="relative mt-5 border-t border-white/20 pt-4 text-xs text-white/75">{{ __('portal.launcher.temayin_note') }}</div>
                </div>
            </div>

        {{-- Accesos rápidos --}}
        <section class="mt-12" aria-labelledby="accesos-rapidos">
            <h2 id="accesos-rapidos" class="font-heading text-sm font-semibold uppercase tracking-wider text-brand-teal">{{ __('portal.launcher.shortcuts') }}</h2>
            <div class="mt-3 flex flex-wrap gap-3">
                @can('approve-accounts')
                    <a href="{{ route('users.index') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-brand-green/10 bg-white px-4 text-sm font-semibold text-brand-green shadow-sm transition hover:border-brand-mint hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
                        <svg class="h-4 w-4 text-brand-teal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM19 8v6M22 11h-6"/></svg>
                        {{ __('portal.launcher.requests') }}
                    </a>
                @endcan
                <a href="{{ route('profile.edit') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-brand-green/10 bg-white px-4 text-sm font-semibold text-brand-green shadow-sm transition hover:border-brand-mint hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
                    <svg class="h-4 w-4 text-brand-teal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>
                    {{ __('portal.launcher.profile') }}
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
