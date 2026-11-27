{{-- Pausa del fondo animado (resources/js/pausa-fondo.js). Oculto hasta que el script confirma que hay movimiento. --}}
<button type="button" hidden data-pausa-fondo data-pausar="{{ __('portal.accessibility.pause') }}" data-reanudar="{{ __('portal.accessibility.resume') }}"
        {{ $attributes->merge(['class' => 'min-h-[32px] rounded-md border border-white/60 bg-black/40 px-2.5 text-xs font-semibold text-white hover:bg-black/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-white']) }}>{{ __('portal.accessibility.pause') }}</button>
