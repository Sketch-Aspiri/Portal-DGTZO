<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-white border border-brand-teal/60 rounded-lg font-semibold text-xs text-brand-teal uppercase tracking-widest hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal focus-visible:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
