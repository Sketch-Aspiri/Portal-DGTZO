{{-- Enlace de los pies de página a la declaración de accesibilidad. --}}
<a href="{{ route('accessibility') }}" {{ $attributes->merge(['class' => 'inline-flex min-h-6 items-center rounded-sm px-1 font-semibold underline underline-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-current']) }}
   @if (request()->routeIs('accessibility')) aria-current="page" @endif>{{ __('portal.accessibility.link') }}</a>
