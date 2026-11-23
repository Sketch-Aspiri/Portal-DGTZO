{{-- Campana de la barra: enlaza al centro de notificaciones con el contador de avisos sin leer. --}}
@php($pendientes = auth()->user()->unreadNotifications()->where('data->type', \App\Notifications\SubsystemNotice::TYPE)->count())
<a href="{{ route('notifications.index') }}" {{ $attributes->merge(['class' => 'relative inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border border-brand-green/15 text-brand-green transition hover:bg-brand-mist focus:outline-none focus:ring-2 focus:ring-brand-teal']) }}
   aria-label="{{ $pendientes > 0 ? trans_choice(':count aviso sin leer|:count avisos sin leer', $pendientes, ['count' => $pendientes]) : __('push.center.title') }}">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg>
    @if ($pendientes > 0)
        <span class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold leading-none text-white" aria-hidden="true">{{ $pendientes > 99 ? '99+' : $pendientes }}</span>
    @endif
</a>
