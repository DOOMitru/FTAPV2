<x-guest-layout>
    <p class="u-muted">
        {{ __("Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.") }}
    </p>

    @if (session('status') === 'verification-link-sent')
        {{-- Was text-green-600 with no dark variant. <x-alert> carries its own
             role and token colours, and reads on both grounds. --}}
        <x-alert variant="success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-alert>
    @endif

    <div class="l-cluster l-cluster--between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-btn variant="primary">{{ __('Resend Verification Email') }}</x-btn>
        </form>

        {{-- Ghost, not an underlined bare button: logging out is a secondary
             action here, and the system already has a variant for that. --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-btn variant="ghost" type="submit">{{ __('Log Out') }}</x-btn>
        </form>
    </div>
</x-guest-layout>
