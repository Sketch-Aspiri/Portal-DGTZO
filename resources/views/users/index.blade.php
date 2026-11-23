<x-app-layout>
    @php
        $statusStyles = [
            'pending' => 'bg-amber-100 text-amber-800',
            'active' => 'bg-green-100 text-green-800',
            'inactive' => 'bg-gray-200 text-gray-700',
        ];
        $systemNames = collect(\App\Support\Subsistemas::todos())->map(fn ($s) => $s['nombre'] ?? null);
    @endphp

    {{-- Banda de título --}}
    <section class="relative isolate overflow-hidden text-white">
        <img src="{{ asset('images/tren-maya.jpeg') }}" alt="" aria-hidden="true"
             class="absolute inset-0 -z-20 h-full w-full scale-[1.3] origin-[10%_70%] object-cover blur-[2px]">
        <div class="absolute inset-0 -z-[15] bg-gradient-to-r from-brand-green-dark/95 via-brand-green/85 to-brand-teal/60"></div>
        <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD" aria-hidden="true"
                class="absolute inset-0 -z-10 h-full w-full opacity-30"></canvas>
        <div class="mx-auto max-w-5xl px-4 pb-20 pt-9 sm:px-6 lg:px-8">
            <h1 class="rise font-heading text-3xl font-bold sm:text-4xl">{{ __('portal.approvals.title') }}</h1>
            <p class="rise mt-2 max-w-2xl text-white/90" style="animation-delay:.08s">{{ __('portal.approvals.subtitle') }}</p>
            <a href="{{ route('users.create') }}" class="rise mt-5 inline-flex min-h-[44px] items-center rounded-xl bg-white px-5 text-sm font-semibold text-brand-green shadow-sm transition hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-green" style="animation-delay:.12s">+ {{ __('portal.approvals.new_account') }}</a>
        </div>
    </section>

    <div class="relative z-10 mx-auto -mt-12 w-full max-w-5xl px-4 pb-16 sm:px-6 lg:px-8">
        @if (session('status'))
            <p class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        {{-- Estados --}}
        <nav class="grid grid-cols-3 gap-3" aria-label="{{ __('portal.approvals.title') }}">
            @foreach (\App\Enums\UserStatus::cases() as $case)
                @php $active = $case === $status; @endphp
                <a href="{{ route('users.index', array_filter(['estado' => $case->value, 'q' => $term])) }}"
                   @if ($active) aria-current="page" @endif
                   class="rise rounded-2xl border p-4 text-left shadow-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal sm:p-5 {{ $active ? 'border-brand-teal bg-white shadow-brand-green/20 ring-1 ring-brand-teal' : 'border-brand-green/10 bg-white/95 shadow-brand-green/10 hover:-translate-y-0.5 hover:border-brand-mint' }}"
                   style="animation-delay: {{ $loop->index * 0.06 }}s">
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider {{ $active ? 'text-brand-teal' : 'text-gray-500' }}">{{ $case->label() }}</span>
                        <span class="h-2 w-2 rounded-full {{ ['pending' => 'bg-amber-500', 'active' => 'bg-green-500', 'inactive' => 'bg-gray-400'][$case->value] }}"></span>
                    </span>
                    <span class="mt-1 block font-heading text-3xl font-bold text-brand-green">{{ $counts[$case->value] ?? 0 }}</span>
                    <span class="mt-0.5 hidden text-xs text-gray-500 sm:block">{{ __('portal.approvals.state_help.'.$case->value) }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Búsqueda --}}
        <form method="get" action="{{ route('users.index') }}" class="mt-5 flex gap-2" role="search">
            <input type="hidden" name="estado" value="{{ $status->value }}">
            <label for="q" class="sr-only">{{ __('portal.approvals.search') }}</label>
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input id="q" name="q" type="search" value="{{ $term }}" maxlength="100" placeholder="{{ __('portal.approvals.search') }}"
                       class="min-h-[44px] w-full rounded-xl border-brand-green/15 bg-white pl-9 text-sm shadow-sm focus:border-brand-teal focus:ring-brand-teal">
            </div>
            <button type="submit" class="min-h-[44px] rounded-xl bg-brand-green px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-green-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal focus-visible:ring-offset-2">{{ __('portal.approvals.search_button') }}</button>
            @if ($term !== '')
                <a href="{{ route('users.index', ['estado' => $status->value]) }}" class="inline-flex min-h-[44px] items-center rounded-xl px-3 text-sm font-semibold text-brand-teal hover:underline">{{ __('portal.approvals.clear') }}</a>
            @endif
        </form>

        @if ($term !== '')
            <p class="mt-3 text-sm text-gray-600">{{ __('portal.approvals.results_for', ['q' => $term]) }} · {{ $users->total() }}</p>
        @endif

        {{-- Lista --}}
        <div class="mt-4 space-y-3">
            @forelse ($users as $row)
                <a href="{{ route('users.show', $row) }}"
                   class="group flex items-center gap-4 rounded-2xl border border-brand-green/10 bg-white p-4 shadow-sm shadow-brand-green/5 transition hover:-translate-y-0.5 hover:border-brand-mint hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal sm:p-5">
                    <x-user-avatar :user="$row" size="lg" />

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="truncate font-heading text-base font-semibold text-brand-green">{{ $row->name }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusStyles[$row->status->value] }}">{{ $row->status->label() }}</span>
                            @if ($row->status === \App\Enums\UserStatus::Pending)
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->solicitudAcceso ? 'bg-brand-mist text-brand-green' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' }}">
                                    {{ __('portal.access_request.states.'.($row->solicitudAcceso ? 'enviada' : 'none')) }}
                                </span>
                            @endif
                        </span>
                        <span class="block truncate text-sm text-gray-600">{{ $row->email }}</span>
                        <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @forelse ($row->accesos->pluck('subsistema')->unique() as $clave)
                                <span class="rounded-md bg-brand-mist px-2 py-0.5 text-xs font-semibold text-brand-teal">{{ $systemNames[$clave] ?? ucfirst($clave) }}</span>
                            @empty
                                <span class="text-xs text-gray-400">{{ __('portal.approvals.no_systems') }}</span>
                            @endforelse
                            <span class="text-xs text-gray-400">· {{ __('portal.approvals.requested') }} {{ $row->created_at->diffForHumans() }}</span>
                        </span>
                    </span>

                    <span class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-brand-teal">
                        <span class="hidden sm:inline">{{ __('portal.approvals.review') }}</span>
                        <svg class="h-5 w-5 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 4.3a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4L11.6 10 7.3 5.7a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
                    </span>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-brand-green/25 bg-white p-10 text-center shadow-sm">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-brand-mist text-brand-teal">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8"/></svg>
                    </span>
                    <p class="mt-4 text-gray-600">{{ __('portal.approvals.empty') }}</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">{{ $users->links() }}</div>
    </div>
</x-app-layout>
