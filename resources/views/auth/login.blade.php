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

    <x-card :title="__('Sign in to your account')" class="p-raised p-auth-form">
        <p class="p-form-head__notes">
            {{ __('Or') }}
            <a class="link" href="{{ route('register') }}">{{ __('create a new account') }}</a>
        </p>

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="l-stack">
            @csrf

            <x-field name="email" type="email" :label="__('Email')" :value="old('email')"
                     required autofocus autocomplete="username" />

            <x-field name="password" type="password" :label="__('Password')"
                     required autocomplete="current-password" />

            <div class="l-cluster l-cluster--between">
                {{-- name="remember" is what the controller reads. Nothing in
                     tests/Feature/Auth asserts it exists, so it is easy to
                     lose in a rewrite. --}}
                <label class="field__check" for="remember_me">
                    <input id="remember_me" type="checkbox" name="remember">
                    <span>{{ __('Remember me') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                @endif
            </div>

            <x-btn variant="primary" class="btn--block">{{ __('Sign in') }}</x-btn>
        </form>
    </x-card>
</x-public-layout>
