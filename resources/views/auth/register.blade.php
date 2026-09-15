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

    <x-card :title="__('Create your account')" class="p-raised p-auth-form">
        <p class="p-form-head__notes">
            {{ __('Or') }}
            <a class="link" href="{{ route('login') }}">{{ __('sign in to your existing account') }}</a>
        </p>

        <form method="POST" action="{{ route('register') }}" class="l-stack">
            @csrf

            {{-- Paired on one row: five stacked fields in a column this
                 narrow is a long scroll, and a first/last name pair is the
                 one place the eye reads two controls as one thing. .l-grid
                 collapses them at 375px without a breakpoint. --}}
            <div class="l-grid">
                <x-field name="first_name" :label="__('First Name')" :value="old('first_name')"
                         required autofocus autocomplete="given-name" />

                <x-field name="last_name" :label="__('Last Name')" :value="old('last_name')"
                         required autocomplete="family-name" />
            </div>

            <x-field name="email" type="email" :label="__('Email')" :value="old('email')"
                     required autocomplete="username" />

            <x-field name="password" type="password" :label="__('Password')"
                     required autocomplete="new-password" />

            <x-field name="password_confirmation" type="password" :label="__('Confirm Password')"
                     required autocomplete="new-password" />

            {{-- Said before the button, not discovered afterwards. Since the
                 approval gate shipped, a new account can browse and hold a
                 profile but cannot enter a tournament until an
                 administrator approves it -- and without this the first a
                 person hears of that is an "Awaiting approval" badge where
                 they expected a Register button. --}}
            <p class="field__hint">
                {{ __('You can sign up straight away. Entering tournaments needs an administrator to approve your account first, and you will need to confirm your email address.') }}
            </p>

            @if (\App\Rules\Recaptcha::configured())
                {{-- Above the button, where it is part of filling the form in
                     rather than something discovered after pressing it.

                     The error is rendered here by hand: <x-field> owns the
                     error line for the controls it draws, and the widget is
                     not one of them -- Google draws it. Without this, a failed
                     check would reload the page with the form apparently
                     intact and nothing said. --}}
                <div class="l-stack l-stack--tight">
                    {{-- The wrapper exists to scale what it holds: Google's
                         widget is a fixed 304x78 and does not shrink, and the
                         form it sits in is 223px wide at 320. --}}
                    <div class="recaptcha">
                        <div class="g-recaptcha"
                             data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                    </div>

                    @error('g-recaptcha-response')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="l-cluster l-cluster--between">
                <a class="link" href="{{ route('login') }}">{{ __('Already registered?') }}</a>

                <x-btn variant="primary">{{ __('Register') }}</x-btn>
            </div>
        </form>
    </x-card>

    @if (\App\Rules\Recaptcha::configured())
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</x-public-layout>
