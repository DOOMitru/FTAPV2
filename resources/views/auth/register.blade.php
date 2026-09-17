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

        {{-- v3 scores the submission rather than asking for a click, so the
             form carries the key and the action and recaptcha.ts fetches a
             token as it is sent. The key travels on a data attribute because
             this project keeps JavaScript out of the document. --}}
        <form method="POST" action="{{ route('register') }}" class="l-stack"
              @if (\App\Rules\Recaptcha::configured())
                  data-recaptcha="{{ config('services.recaptcha.site_key') }}"
                  data-recaptcha-action="register"
              @endif>
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
                {{-- Nothing to see: v3 has no widget, and the token is written
                     here as the form is submitted.

                     The error is rendered by hand because <x-field> owns the
                     error line only for controls it draws, and this one is
                     hidden. Without it a refused score would reload the page
                     with the form apparently intact and nothing said -- the
                     worst failure this feature can have, because the person
                     has no widget to look at for a clue. --}}
                <input type="hidden" name="g-recaptcha-response">

                @error('g-recaptcha-response')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            @endif

            <div class="l-cluster l-cluster--between">
                <a class="link" href="{{ route('login') }}">{{ __('Already registered?') }}</a>

                <x-btn variant="primary">{{ __('Register') }}</x-btn>
            </div>
        </form>
    </x-card>

    @if (\App\Rules\Recaptcha::configured())
        {{-- ?render= is what makes this v3: it loads the scoring API for this
             key instead of drawing a checkbox. Google's badge appears bottom
             right; it is not hidden, because hiding it obliges the site to
             carry Google's wording instead, and a badge is less intrusive than
             a paragraph. --}}
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}" async defer></script>
    @endif
</x-public-layout>
