<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-brand-green border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-green-dark focus:bg-brand-green-dark active:bg-brand-green-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal focus-visible:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
