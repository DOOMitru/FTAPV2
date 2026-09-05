<x-public-layout>
    {{-- Mark and name only. The lede went with the reason cards: on a page
         whose whole job is one form, a paragraph of pitch is something to read
         past rather than something that helps. --}}
    <div class="p-auth-intro">
        <img class="p-auth-intro__mark" src="{{ asset('images/hero_logo.png') }}" alt="">

        <x-p-hero align="start" plain
                  :title="__('First to Act Poker League')"
                  :highlight="__('Poker League')" />
    </div>

    <x-card :title="__('Reset your password')" class="p-raised p-auth-form">
        <p class="p-form-head__notes">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </p>

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="l-stack">
            @csrf

            <x-field name="email" type="email" :label="__('Email')" :value="old('email')"
                     required autofocus />

            <div class="l-cluster l-cluster--between">
                <a class="link" href="{{ route('login') }}">{{ __('Back to login') }}</a>

                <x-btn variant="primary">{{ __('Email Password Reset Link') }}</x-btn>
            </div>
        </form>
    </x-card>
</x-public-layout>
