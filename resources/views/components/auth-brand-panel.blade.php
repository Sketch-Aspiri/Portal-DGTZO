{{-- Panel de marca del acceso: foto del tren, capa verde y seda animada. --}}
<div {{ $attributes->merge(['class' => 'relative isolate overflow-hidden text-white']) }}>
    <img src="{{ asset('images/tren-maya.jpeg') }}" alt="" aria-hidden="true"
         class="absolute inset-0 -z-20 h-full w-full scale-[1.4] origin-[15%_70%] object-cover blur-[2px]">
    <div class="absolute inset-0 -z-[15] bg-gradient-to-br from-brand-green-dark/95 via-brand-green/80 to-brand-teal/60"></div>
    <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD" aria-hidden="true"
            class="absolute inset-0 -z-10 h-full w-full opacity-40"></canvas>
    <x-pausa-fondo class="absolute right-2 top-2" />

    {{ $slot }}
</div>
