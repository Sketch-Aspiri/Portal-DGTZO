<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
     x-data="{
        q: '',
        normal(t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
        coincide(texto) { return this.normal(texto).includes(this.normal(this.q.trim())) },
     }">
    <h1 class="font-heading text-3xl font-bold text-brand-green">{{ __('portal.tutorials.title') }}</h1>
    <p class="mt-2 max-w-2xl text-gray-600">{{ __($completa ? 'portal.tutorials.subtitle' : 'portal.tutorials.subtitle_guest') }}</p>

    @if ($grupos->isEmpty())
        <p class="mt-8 rounded-xl border border-dashed border-brand-green/25 bg-white p-6 text-gray-600">{{ __('portal.tutorials.empty') }}</p>
    @else
        <label for="buscar-guia" class="sr-only">{{ __('portal.tutorials.search') }}</label>
        <input id="buscar-guia" type="search" x-model="q" placeholder="{{ __('portal.tutorials.search') }}" autocomplete="off"
               class="mt-6 block min-h-[44px] w-full max-w-md rounded-lg border-gray-300 shadow-sm focus:border-brand-teal focus:ring-brand-teal">
    @endif

    @foreach ($sistemas as $clave => $nombre)
        @continue(! $grupos->has($clave))
        @php $textos = $grupos[$clave]->map(fn ($g) => $nombre.' '.$g->titulo.' '.$g->resumen)->values(); @endphp
        <section class="mt-10" aria-labelledby="sistema-{{ $clave }}" x-show="{{ Js::from($textos) }}.some(t => coincide(t))">
            <h2 id="sistema-{{ $clave }}" class="font-heading text-sm font-semibold uppercase tracking-wider text-brand-teal">{{ $nombre }}</h2>
            <ul class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($grupos[$clave] as $guia)
                    <li class="flex" x-show="coincide({{ Js::from($nombre.' '.$guia->titulo.' '.$guia->resumen) }})">
                        <a href="{{ route('tutorials.show', $guia->slug) }}"
                           class="flex w-full flex-col rounded-2xl border border-brand-green/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-mint hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
                            <span class="flex items-start justify-between gap-3">
                                <span class="font-heading text-lg font-semibold text-brand-green">{{ $guia->titulo }}</span>
                                @if ($novedad = $guia->novedad(config('guias.dias_novedad')))
                                    <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ __('portal.tutorials.badge.'.$novedad) }}</span>
                                @endif
                            </span>
                            <span class="mt-2 flex-1 text-sm leading-relaxed text-gray-600">{{ $guia->resumen }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    @if ($grupos->isNotEmpty())
        @php $todos = $grupos->flatten(1)->map(fn ($g) => $sistemas[$g->sistema].' '.$g->titulo.' '.$g->resumen)->values(); @endphp
        <p class="mt-8 text-sm text-gray-600" x-cloak x-show="! {{ Js::from($todos) }}.some(t => coincide(t))">{{ __('portal.tutorials.no_results') }}</p>
    @endif
</div>
