<x-guest-layout>
    @if ($status === \App\Enums\UserStatus::Pending)
        <h1 class="text-lg font-semibold">{{ __('portal.account.pending_title') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('portal.account.pending_text') }}</p>
    @else
        <h1 class="text-lg font-semibold">{{ __('portal.account.inactive_title') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('portal.account.inactive_text') }}</p>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <x-secondary-button type="submit">{{ __('Log Out') }}</x-secondary-button>
    </form>
</x-guest-layout>
