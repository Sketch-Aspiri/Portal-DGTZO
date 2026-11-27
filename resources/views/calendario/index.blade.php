<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-heading font-semibold text-xl text-brand-green leading-tight">{{ __('portal.calendar.title') }}</h1>
                <p class="text-sm text-gray-600">{{ __('portal.calendar.subtitle') }}</p>
            </div>
            @can('manage-calendar')
                <a href="{{ route('calendar.events.create') }}" class="inline-flex min-h-[44px] items-center rounded-md bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">{{ __('portal.calendar.new') }}</a>
            @endcan
        </div>
    </x-slot>

    @php
        $tipos = \App\Enums\CalendarioTipo::cases();
        $mesTexto = ucfirst($mes->locale('es')->isoFormat('MMMM YYYY'));
        $consulta = fn (array $extra = []) => array_filter(['mes' => $mes->format('Y-m'), 'tipo' => $tipo?->value, 'capas' => $capasActivas ?: null, ...$extra], fn ($v) => $v !== null);
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p data-aviso class="rounded-lg bg-green-100 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            {{-- Navegación de mes y filtro por tipo --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <a href="{{ route('calendar.index', $consulta(['mes' => $previo])) }}" aria-label="{{ __('portal.calendar.previous') }}"
                       class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-brand-green/15 bg-white text-brand-green hover:border-brand-mint focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">‹</a>
                    <h2 class="min-w-[10rem] text-center font-heading text-lg font-semibold text-brand-green">{{ $mesTexto }}</h2>
                    <a href="{{ route('calendar.index', $consulta(['mes' => $siguiente])) }}" aria-label="{{ __('portal.calendar.next') }}"
                       class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-brand-green/15 bg-white text-brand-green hover:border-brand-mint focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">›</a>
                    <a href="{{ route('calendar.index', array_filter(['tipo' => $tipo?->value, 'capas' => $capasActivas ?: null])) }}"
                       class="ms-1 inline-flex min-h-[44px] items-center rounded-md border border-brand-green/15 bg-white px-3 text-sm font-semibold text-brand-teal hover:border-brand-mint focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">{{ __('portal.calendar.today') }}</a>
                </div>
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('portal.calendar.types.evento') }}">
                    <a href="{{ route('calendar.index', array_filter(['mes' => $mes->format('Y-m'), 'capas' => $capasActivas ?: null])) }}"
                       class="inline-flex min-h-[44px] items-center rounded-full px-3 text-sm font-semibold {{ $tipo === null ? 'bg-brand-green text-white' : 'bg-white text-brand-green border border-brand-green/15' }}">{{ __('portal.calendar.all_types') }}</a>
                    @foreach ($tipos as $t)
                        <a href="{{ route('calendar.index', array_filter(['mes' => $mes->format('Y-m'), 'tipo' => $t->value, 'capas' => $capasActivas ?: null])) }}"
                           class="inline-flex min-h-[44px] items-center rounded-full px-3 text-sm font-semibold {{ $tipo === $t ? 'bg-brand-green text-white' : 'bg-white text-brand-green border border-brand-green/15' }}">{{ $t->label() }}</a>
                    @endforeach
                </div>
            </div>

            @if ($capasDisponibles)
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('portal.calendar.layers.title') }}">
                    <span class="text-xs font-semibold uppercase tracking-wide text-brand-teal">{{ __('portal.calendar.layers.title') }}</span>
                    @foreach ($capasDisponibles as $capa)
                        @php
                            $encendida = in_array($capa, $capasActivas, true);
                            $nuevas = $encendida ? array_values(array_diff($capasActivas, [$capa])) : [...$capasActivas, $capa];
                        @endphp
                        <a href="{{ route('calendar.index', array_filter(['mes' => $mes->format('Y-m'), 'tipo' => $tipo?->value, 'capas' => $nuevas ?: null])) }}"
                           aria-pressed="{{ $encendida ? 'true' : 'false' }}"
                           class="inline-flex min-h-[44px] items-center rounded-full px-3 text-sm font-semibold {{ $encendida ? 'bg-brand-green text-white' : 'bg-white text-brand-green border border-brand-green/15' }}">{{ __('portal.calendar.layers.'.$capa) }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Cuadrícula mensual (pantallas medianas y grandes) --}}
            <div class="hidden overflow-hidden rounded-xl border border-brand-green/10 bg-white shadow-sm md:block">
                <div class="grid grid-cols-7 border-b border-brand-green/10 bg-brand-mist/50 text-center text-xs font-semibold uppercase tracking-wide text-brand-teal">
                    @foreach (__('portal.calendar.days') as $nombre)
                        <div class="py-2">{{ $nombre }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7">
                    @for ($i = 0; $i < $semanas * 7; $i++)
                        @php
                            $dia = $desde->addDays($i);
                            $clave = $dia->format('Y-m-d');
                            $delMes = $dia->month === $mes->month;
                            $entradas = $porDia[$clave] ?? [];
                        @endphp
                        <div class="min-h-[6.5rem] border-b border-e border-brand-green/5 p-1.5 {{ $delMes ? '' : 'bg-gray-50/70' }}">
                            <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-1 text-xs font-semibold {{ $dia->isSameDay($hoy) ? 'bg-brand-green text-white' : ($delMes ? 'text-gray-700' : 'text-gray-400') }}">{{ $dia->day }}</span>
                            <ul class="mt-1 space-y-1">
                                @foreach (array_slice($entradas, 0, 3) as $entrada)
                                    <li>
                                        @if ($entrada->editable() && auth()->user()->can('manage-calendar'))
                                            <a href="{{ route('calendar.events.edit', $entrada) }}" title="{{ $entrada->titulo }}" class="block truncate rounded px-1.5 py-0.5 text-xs font-medium {{ $entrada->chip() }} focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">{{ $entrada->titulo }}</a>
                                        @else
                                            <span title="{{ $entrada->etiqueta() }}: {{ $entrada->titulo }}" class="block truncate rounded px-1.5 py-0.5 text-xs font-medium {{ $entrada->chip() }}">{{ $entrada->titulo }}</span>
                                        @endif
                                    </li>
                                @endforeach
                                @if (count($entradas) > 3)
                                    <li class="px-1.5 text-xs text-gray-500">{{ __('portal.calendar.more', ['count' => count($entradas) - 3]) }}</li>
                                @endif
                            </ul>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Agenda del mes (celulares): solo los días con entradas --}}
            <div class="space-y-2 md:hidden">
                @php $hay = false; @endphp
                @foreach ($porDia as $clave => $entradas)
                    @continue(! str_starts_with($clave, $mes->format('Y-m')))
                    @php $hay = true; $dia = \Carbon\CarbonImmutable::parse($clave)->locale('es'); @endphp
                    <div class="rounded-xl border border-brand-green/10 bg-white p-3 shadow-sm">
                        <p class="text-sm font-semibold text-brand-green">{{ ucfirst($dia->isoFormat('dddd D')) }}</p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($entradas as $entrada)
                                <li class="flex items-start gap-2 text-sm">
                                    <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-xs font-medium {{ $entrada->chip() }}">{{ $entrada->etiqueta() }}</span>
                                    <span class="min-w-0 flex-1 text-gray-800">{{ $entrada->titulo }}</span>
                                    @if ($entrada->editable() && auth()->user()->can('manage-calendar'))
                                        <a href="{{ route('calendar.events.edit', $entrada) }}" class="inline-flex min-h-[44px] items-center text-xs font-semibold text-brand-teal underline">{{ __('portal.calendar.edit') }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                @unless ($hay)
                    <p class="rounded-xl border border-dashed border-brand-green/25 bg-white p-5 text-sm text-gray-600">{{ __('portal.calendar.nothing_upcoming') }}</p>
                @endunless
            </div>

            {{-- Próximas entradas --}}
            <section aria-labelledby="proximas">
                <h2 id="proximas" class="font-heading text-sm font-semibold uppercase tracking-wider text-brand-teal">{{ __('portal.calendar.upcoming') }}</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($proximas as $entrada)
                        @php $inicio = $entrada->inicioLocal()->locale('es'); @endphp
                        <div class="rounded-xl border border-brand-green/10 bg-white p-4 shadow-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded px-1.5 py-0.5 text-xs font-medium {{ $entrada->tipo->chip() }}">{{ $entrada->tipo->label() }}</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $entrada->titulo }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ ucfirst($inicio->isoFormat('ddd D MMM YYYY')) }} ·
                                {{ $entrada->todo_el_dia ? __('portal.calendar.all_day') : $inicio->format('H:i').($entrada->finLocal() ? ' – '.$entrada->finLocal()->format('H:i') : '') }}
                                @if ($entrada->lugar) · {{ $entrada->lugar }} @endif
                            </p>
                            @if ($entrada->descripcion)
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $entrada->descripcion }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-brand-green/25 bg-white p-5 text-sm text-gray-600">{{ __('portal.calendar.nothing_upcoming') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
