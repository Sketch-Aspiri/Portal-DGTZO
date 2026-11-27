<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
     x-data="{
        captura: null,
        texto: '',
        origen: null,
        abrir(evento) {
            const boton = evento.target.closest('[data-captura]');
            if (! boton) return;
            this.origen = boton;
            this.captura = boton.dataset.captura;
            this.texto = boton.querySelector('img').alt;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.cerrar.focus());
        },
        cerrar() {
            if (! this.captura) return;
            this.captura = null;
            document.documentElement.classList.remove('overflow-hidden');
            this.$nextTick(() => this.origen.focus());
        },
     }"
     @keydown.escape.window="cerrar()">
    <a href="{{ route('tutorials.index') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-brand-teal hover:underline">&larr; {{ __('portal.tutorials.back') }}</a>

    <header class="mt-2">
        <p class="text-sm font-semibold uppercase tracking-wider text-brand-teal">{{ $sistema }}</p>
        <h1 class="mt-1 font-heading text-3xl font-bold text-brand-green">{{ $guia->titulo }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ __('portal.tutorials.updated', ['date' => $guia->actualizada->locale('es')->isoFormat('D [de] MMMM [de] YYYY')]) }}</p>
    </header>

    <div class="mt-6 flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1fr)_16rem] lg:items-start lg:gap-8">
        <article class="order-2 min-w-0 lg:order-1">
            <div class="guia-contenido rounded-2xl border border-brand-green/10 bg-white p-5 sm:p-8" @click="abrir($event)">
                {!! $html !!}
            </div>

            <nav class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="{{ __('portal.tutorials.more') }}">
                @if ($anterior)
                    <a href="{{ route('tutorials.show', $anterior->slug) }}" class="rounded-xl border border-brand-green/10 bg-white p-4 hover:border-brand-mint">
                        <span class="block text-xs text-gray-500">{{ __('portal.tutorials.previous') }}</span>
                        <span class="block font-semibold text-brand-green">{{ $anterior->titulo }}</span>
                    </a>
                @endif
                @if ($siguiente)
                    <a href="{{ route('tutorials.show', $siguiente->slug) }}" class="rounded-xl border border-brand-green/10 bg-white p-4 text-right hover:border-brand-mint sm:col-start-2">
                        <span class="block text-xs text-gray-500">{{ __('portal.tutorials.next') }}</span>
                        <span class="block font-semibold text-brand-green">{{ $siguiente->titulo }}</span>
                    </a>
                @endif
            </nav>
        </article>

        @if (count($indice) > 1)
            <aside class="order-1 lg:sticky lg:top-4 lg:order-2">
                <nav class="rounded-2xl border border-brand-green/10 bg-white p-4" aria-label="{{ __('portal.tutorials.contents') }}">
                    <p class="font-heading text-sm font-semibold text-brand-green">{{ __('portal.tutorials.contents') }}</p>
                    <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-gray-600">
                        @foreach ($indice as $paso)
                            <li><a href="#{{ $paso['id'] }}" class="hover:text-brand-teal hover:underline">{{ $paso['titulo'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </aside>
        @endif
    </div>

    {{-- Captura ampliada --}}
    <div x-cloak x-show="captura" class="fixed inset-0 z-50 flex flex-col gap-3 bg-black/85 p-4" role="dialog" aria-modal="true"
         aria-label="{{ __('portal.tutorials.zoomed') }}" :aria-label="texto || {{ Js::from(__('portal.tutorials.zoomed')) }}" @click="cerrar()">
        <button type="button" x-ref="cerrar" class="inline-flex min-h-[44px] shrink-0 items-center self-end rounded-lg bg-white px-4 text-sm font-semibold text-gray-900">{{ __('portal.tutorials.close') }}</button>
        {{-- Con foco propio para desplazarse con las flechas cuando la imagen es más grande que la pantalla. --}}
        <div class="min-h-0 flex-1 overflow-auto rounded-lg focus:outline-none focus-visible:ring-4 focus-visible:ring-white" tabindex="0" role="region" aria-label="{{ __('portal.tutorials.zoomed_scroll') }}">
            <img src="data:," alt="" :src="captura || 'data:,'" :alt="texto" class="mx-auto max-w-none sm:max-w-full">
        </div>
    </div>
</div>
