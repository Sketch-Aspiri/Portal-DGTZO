<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-semibold text-xl text-brand-green leading-tight">{{ __('portal.approvals.title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-4">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-800 bg-green-100 rounded-lg px-3 py-2">{{ session('status') }}</p>
            @endif

            <nav class="mb-4 flex gap-4 text-sm" aria-label="{{ __('portal.approvals.title') }}">
                @foreach (\App\Enums\UserStatus::cases() as $case)
                    <a href="{{ route('users.index', ['estado' => $case->value]) }}"
                       @if ($case === $status) aria-current="page" @endif
                       class="{{ $case === $status ? 'font-semibold text-gray-900 underline' : 'text-gray-600' }}">{{ $case->label() }}</a>
                @endforeach
            </nav>

            <div class="bg-white border border-brand-green/10 shadow-sm shadow-brand-green/5 rounded-xl divide-y">
                @forelse ($users as $row)
                    <div class="flex items-center justify-between gap-4 p-4">
                        <div class="min-w-0">
                            <p class="font-medium truncate">{{ $row->name }}</p>
                            <p class="text-sm text-gray-500 truncate">{{ $row->email }}</p>
                            <p class="text-xs text-gray-400">{{ __('portal.approvals.requested') }} {{ $row->created_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('users.show', $row) }}" class="text-sm font-semibold text-brand-teal underline hover:text-brand-green-dark">{{ __('portal.approvals.review') }}</a>
                    </div>
                @empty
                    <p class="p-6 text-gray-600">{{ __('portal.approvals.empty') }}</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
