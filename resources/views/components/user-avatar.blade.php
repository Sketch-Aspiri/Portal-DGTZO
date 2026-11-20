@props(['user' => null, 'size' => 'md'])

{{-- Foto de perfil redonda; sin foto, la inicial del nombre. Decorativa: el nombre ya va al lado. --}}
@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-9 w-9 text-sm', 'lg' => 'h-14 w-14 text-xl', 'xl' => 'h-28 w-28 text-4xl sm:h-32 sm:w-32'];
    $classes = 'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-teal to-brand-green font-heading font-semibold uppercase text-white '.($sizes[$size] ?? $sizes['md']);
    $url = $user?->avatarUrl();
@endphp

<span {{ $attributes->merge(['class' => $classes]) }} aria-hidden="true">
    @if ($url)
        <img src="{{ $url }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
    @else
        {{ mb_substr((string) ($user?->name ?? '?'), 0, 1) }}
    @endif
</span>
