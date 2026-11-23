<x-app-layout>
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-heading text-2xl font-bold text-brand-green">{{ __('push.center.title') }}</h1>
                @if ($unread > 0)
                    <p class="mt-1 text-sm text-gray-600">{{ trans_choice(':count aviso sin leer|:count avisos sin leer', $unread, ['count' => $unread]) }}</p>
                @endif
            </div>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg border border-brand-green/30 bg-white px-4 text-sm font-semibold text-brand-green hover:bg-brand-mist focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">
                        {{ __('push.center.mark_all') }}
                    </button>
                </form>
            @endif
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg bg-green-100 px-3 py-2 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            {{-- Historial --}}
            <section aria-label="{{ __('push.center.title') }}">
                @forelse ($notifications as $notification)
                    @php($unreadItem = $notification->read_at === null)
                    <a href="{{ route('notifications.open', $notification->id) }}"
                       class="mb-3 flex gap-3 rounded-2xl border bg-white p-4 shadow-sm shadow-brand-green/5 transition hover:border-brand-mint focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal {{ $unreadItem ? 'border-brand-teal/40' : 'border-brand-green/10' }}">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $unreadItem ? 'bg-brand-teal' : 'bg-gray-200' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm {{ $unreadItem ? 'font-semibold text-gray-900' : 'font-medium text-gray-700' }}">{{ $notification->data['title'] ?? '' }}</span>
                                <span class="rounded-full bg-brand-mist px-2 py-0.5 text-xs font-semibold text-brand-teal">{{ __('push.systems.'.($notification->data['source'] ?? 'portal')) }}</span>
                                @if ($unreadItem)<span class="sr-only">{{ __('push.center.unread') }}</span>@endif
                            </span>
                            <span class="mt-0.5 block text-sm text-gray-600">{{ $notification->data['body'] ?? '' }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-brand-green/25 bg-white p-10 text-center text-sm text-gray-600">
                        {{ __('push.center.empty') }}
                    </div>
                @endforelse

                <div class="mt-4">{{ $notifications->links() }}</div>
            </section>

            {{-- Este dispositivo y avisos por sistema --}}
            <aside class="space-y-4">
                <x-push-toggle />

                <section class="rounded-2xl border border-brand-green/10 bg-white p-5 shadow-sm shadow-brand-green/5">
                    <h2 class="font-heading text-base font-semibold text-brand-green">{{ __('push.center.preferences') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('push.center.preferences_intro') }}</p>
                    {{-- Un interruptor por sistema: encendido = avisos activados, apagado = silenciado. Con JavaScript se guarda
                         al instante (fetch); sin él, el botón Guardar envía el formulario normal. --}}
                    <form method="POST" action="{{ route('notifications.preferences') }}" class="mt-3"
                          x-data="{
                              estado: '',
                              async guardar(form) {
                                  this.estado = 'guardando';
                                  try {
                                      const r = await fetch(form.action, {
                                          method: 'POST',
                                          credentials: 'same-origin',
                                          headers: { 'Accept': 'application/json' },
                                          body: new FormData(form),
                                      });
                                      if (!r.ok) { throw new Error('guardar'); }
                                      this.estado = 'guardado';
                                      setTimeout(() => { if (this.estado === 'guardado') { this.estado = ''; } }, 2500);
                                      return true;
                                  } catch (e) {
                                      this.estado = 'error';
                                      return false;
                                  }
                              },
                          }">
                        @csrf
                        @method('PUT')
                        <ul class="divide-y divide-brand-green/10">
                            @foreach ($systems as $system)
                                <li x-data="{ activo: {{ in_array($system, $muted, true) ? 'false' : 'true' }} }"
                                    class="flex min-h-[56px] items-center justify-between gap-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ __('push.systems.'.$system) }}</p>
                                        <p class="text-xs font-medium" :class="activo ? 'text-green-700' : 'text-gray-500'" aria-live="polite">
                                            <span x-text="activo ? {{ Js::from(__('push.center.state_on')) }} : {{ Js::from(__('push.center.state_muted')) }}">{{ in_array($system, $muted, true) ? __('push.center.state_muted') : __('push.center.state_on') }}</span>
                                        </p>
                                    </div>
                                    <label class="relative inline-flex min-h-[44px] min-w-[52px] shrink-0 cursor-pointer items-center justify-end">
                                        <input type="checkbox" role="switch" name="active[{{ $system }}]" value="1"
                                               aria-label="{{ __('push.center.switch_label', ['system' => __('push.systems.'.$system)]) }}"
                                               @checked(! in_array($system, $muted, true))
                                               x-model="activo"
                                               @change="guardar($event.target.form).then(ok => { if (!ok) { activo = !activo; } })"
                                               class="peer sr-only">
                                        <span class="relative block h-7 w-12 rounded-full bg-gray-400 transition-colors peer-checked:bg-brand-green peer-focus-visible:ring-2 peer-focus-visible:ring-brand-teal peer-focus-visible:ring-offset-2 after:absolute after:left-0.5 after:top-0.5 after:h-6 after:w-6 after:rounded-full after:bg-[#ffffff] after:shadow after:transition-transform after:content-[''] peer-checked:after:translate-x-5" aria-hidden="true"></span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 min-h-[1.25rem] text-xs" role="status" aria-live="polite">
                            <span x-show="estado === 'guardando'" x-cloak class="text-gray-500">{{ __('push.center.saving') }}</span>
                            <span x-show="estado === 'guardado'" x-cloak class="font-medium text-green-700">{{ __('push.center.saved_short') }}</span>
                            <span x-show="estado === 'error'" x-cloak class="font-medium text-red-600">{{ __('push.center.save_error') }}</span>
                        </p>
                        <noscript>
                            <button type="submit" class="mt-2 inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-brand-green px-4 text-sm font-semibold text-white">{{ __('push.center.save_button') }}</button>
                        </noscript>
                    </form>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
