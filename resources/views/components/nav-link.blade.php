@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-brand-teal text-sm font-medium leading-5 text-brand-green focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-teal focus:border-brand-green-dark transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-brand-mint focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-teal focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
