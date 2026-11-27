<x-app-layout>
    @php
        $editando = $evento->exists;
        $inicio = $editando ? $evento->inicioLocal() : null;
        $fin = $editando ? $evento->finLocal() : null;
        $diaCompleto = old('todo_el_dia', $editando ? $evento->todo_el_dia : false);
        $accion = $editando ? route('calendar.events.update', $evento) : route('calendar.events.store');
    @endphp

    <x-slot name="header">
        <h1 class="font-heading font-semibold text-xl text-brand-green leading-tight">{{ $editando ? __('portal.calendar.edit_title') : __('portal.calendar.new_title') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $accion }}" class="space-y-4 rounded-xl border border-brand-green/10 bg-white p-6 shadow-sm shadow-brand-green/5"
                  x-data="{ tipo: @js(old('tipo', $evento->tipo->value)), completo: @js((bool) $diaCompleto) }">
                @csrf
                @if ($editando) @method('PUT') @endif

                <p class="text-sm text-gray-600">{{ __('portal.calendar.form.timezone_help') }}</p>

                <div>
                    <x-input-label for="tipo" :value="__('portal.calendar.form.type')" />
                    <select id="tipo" name="tipo" x-model="tipo" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($tipos as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="titulo" :value="__('portal.calendar.form.title')" />
                    <x-text-input id="titulo" name="titulo" type="text" class="mt-1 block w-full" :value="old('titulo', $evento->titulo)" required maxlength="120" />
                    <x-input-error :messages="$errors->get('titulo')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="lugar" :value="__('portal.calendar.form.place')" />
                    <x-text-input id="lugar" name="lugar" type="text" class="mt-1 block w-full" :value="old('lugar', $evento->lugar)" maxlength="120" />
                    <x-input-error :messages="$errors->get('lugar')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="descripcion" :value="__('portal.calendar.form.description')" />
                    <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('descripcion', $evento->descripcion) }}</textarea>
                    <x-input-error :messages="$errors->get('descripcion')" class="mt-1" />
                </div>

                <div x-show="tipo !== 'inhabil'">
                    <label class="inline-flex min-h-[44px] items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" name="todo_el_dia" value="1" x-model="completo" class="rounded border-gray-300 text-brand-green focus:ring-brand-teal">
                        {{ __('portal.calendar.form.all_day') }}
                    </label>
                </div>
                <p class="text-xs text-gray-500" x-show="tipo === 'inhabil'">{{ __('portal.calendar.form.all_day_help') }}</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="fecha_inicio" :value="__('portal.calendar.form.start_date')" />
                        <x-text-input id="fecha_inicio" name="fecha_inicio" type="date" class="mt-1 block w-full" :value="old('fecha_inicio', $inicio?->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('fecha_inicio')" class="mt-1" />
                    </div>
                    <div x-show="tipo !== 'inhabil' && !completo">
                        <x-input-label for="hora_inicio" :value="__('portal.calendar.form.start_time')" />
                        <x-text-input id="hora_inicio" name="hora_inicio" type="time" class="mt-1 block w-full" :value="old('hora_inicio', ! $diaCompleto ? $inicio?->format('H:i') : null)" />
                        <x-input-error :messages="$errors->get('hora_inicio')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fecha_fin" :value="__('portal.calendar.form.end_date')" />
                        <x-text-input id="fecha_fin" name="fecha_fin" type="date" class="mt-1 block w-full" :value="old('fecha_fin', $fin?->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('fecha_fin')" class="mt-1" />
                    </div>
                    <div x-show="tipo !== 'inhabil' && !completo">
                        <x-input-label for="hora_fin" :value="__('portal.calendar.form.end_time')" />
                        <x-text-input id="hora_fin" name="hora_fin" type="time" class="mt-1 block w-full" :value="old('hora_fin', ! $diaCompleto ? $fin?->format('H:i') : null)" />
                        <x-input-error :messages="$errors->get('hora_fin')" class="mt-1" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('portal.calendar.save') }}</x-primary-button>
                    <a href="{{ route('calendar.index', ['mes' => ($inicio ?? now(config('app.display_timezone')))->format('Y-m')]) }}" class="text-sm text-gray-700 underline">{{ __('portal.calendar.cancel') }}</a>
                </div>
            </form>

            @if ($editando)
                <details class="rounded-xl border border-red-200 bg-white p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-red-700">{{ __('portal.calendar.delete') }}</summary>
                    <form method="POST" action="{{ route('calendar.events.destroy', $evento) }}" class="mt-3 flex items-center gap-3">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm text-gray-700">{{ __('portal.calendar.confirm_delete') }}</p>
                        <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">{{ __('portal.calendar.delete') }}</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
