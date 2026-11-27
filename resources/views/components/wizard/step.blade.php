{{-- Tarjeta de un paso del asistente. Se despliega (grid 0fr→1fr) cuando es el paso activo. Usa el estado de accessWizard. --}}
@props(['index', 'title', 'help'])

<section id="wizard-step-{{ $index }}"
         class="scroll-mt-4 rounded-2xl border bg-white transition-all duration-300 motion-reduce:transition-none"
         :class="[
             step === {{ $index }} ? 'border-brand-teal/40 shadow-lg shadow-brand-green/10' : 'border-brand-green/10 shadow-sm',
             {{ $index }} > reached ? 'border-dashed' : ''
         ]">
    <header class="flex items-center gap-3 p-4 sm:p-5">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-heading text-sm font-bold transition-colors duration-300 motion-reduce:transition-none"
              :class="done({{ $index }}) ? 'bg-brand-green text-white' : (step === {{ $index }} ? 'bg-brand-teal text-white' : 'bg-brand-mist text-brand-teal')">
            <span x-show="! done({{ $index }})">{{ $index + 1 }}</span>
            <svg x-show="done({{ $index }})" x-cloak class="wiz-pop h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </span>

        <div class="min-w-0 flex-1">
            <h2 class="font-heading font-semibold text-brand-green">{{ $title }}</h2>
            <p class="text-sm text-gray-500" x-show="step !== {{ $index }}">{{ $help }}</p>
        </div>

        <button type="button" x-show="reached >= {{ $index }} && step !== {{ $index }}" x-cloak @click="go({{ $index }})"
                class="min-h-[44px] rounded-lg px-3 text-sm font-semibold text-brand-teal hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
            {{ __('portal.access_request.edit') }}
        </button>
    </header>

    <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-out motion-reduce:transition-none"
         :class="step === {{ $index }} ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
         :inert="step !== {{ $index }}">
        <div class="min-h-0 overflow-hidden">
            <div class="px-4 pb-5 sm:px-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
