@props(['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password'])

<div x-data="{ show: false }" class="relative mt-1">
    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-teal" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 118 0v3"/></svg>
    <input id="{{ $id }}" name="{{ $name }}" :type="show ? 'text' : 'password'" required
           autocomplete="{{ $autocomplete }}"
           {{ $attributes->merge(['class' => 'block w-full min-h-[44px] rounded-lg border-gray-300 pl-10 pr-12 shadow-sm focus:border-brand-teal focus:ring-brand-teal']) }}>
    <button type="button" @click="show = ! show"
            :aria-label="show ? @js(__('portal.auth.hide_password')) : @js(__('portal.auth.show_password'))"
            class="absolute right-1 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-lg text-gray-500 hover:text-brand-green focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
        <svg x-show="! show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg x-show="show" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A10 10 0 0112 5c6.5 0 10 7 10 7a17 17 0 01-3.2 4.2M6.6 6.6A17 17 0 002 12s3.5 7 10 7a9.8 9.8 0 004.1-.9M9.9 9.9a3 3 0 004.2 4.2"/></svg>
    </button>
</div>
