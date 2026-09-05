<x-guest-layout>
    <p class="u-muted">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="l-stack">
        @csrf

        <x-field name="password" type="password" :label="__('Password')" required autocomplete="current-password" />

        <div class="l-cluster l-cluster--end">
            <x-btn variant="primary">{{ __('Confirm') }}</x-btn>
        </div>
    </form>
</x-guest-layout>
