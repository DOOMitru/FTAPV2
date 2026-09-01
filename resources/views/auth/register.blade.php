<x-public-layout>
    <div class="p-split">
        <x-card :title="__('Create your account')" class="p-raised">
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

                <div class="l-cluster l-cluster--between">
                    <a class="link" href="{{ route('login') }}">{{ __('Already registered?') }}</a>

                    <x-btn variant="primary">{{ __('Register') }}</x-btn>
                </div>
            </form>
        </x-card>

        {{-- The aside, in a wrapper. All three of these views were missing one,
             so the aside's three children became direct grid items and the
             two-column layout laid out 2x2 -- the lede stranded bottom left,
             far from the heading it belongs to. --}}
        <div class="l-stack">
            <img class="p-auth-logo" src="{{ asset('images/hero_logo.png') }}" alt="">

            <x-p-hero align="start" plain
                      :title="__('First to Act Poker League')"
                      :highlight="__('Poker League')">
                {{ __('Join the most exciting amateur poker league. Compete in tournaments, climb the leaderboard, and become the champion.') }}
            </x-p-hero>

            @php
                $reasons = [
                    ['title' => 'Regular Tournaments', 'text' => 'Compete in weekly tournaments with points counting towards the season finale.'],
                    ['title' => 'Fair Play', 'text' => 'Strict adherence to rules ensuring a fair game for everyone.'],
                    ['title' => 'Community', 'text' => 'Join a passionate community of poker enthusiasts.'],
                ];
                $tick = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
            @endphp

            <div class="l-stack l-stack--tight">
                @foreach ($reasons as $reason)
                    <x-p-item :icon="$tick" :title="__($reason['title'])">{{ __($reason['text']) }}</x-p-item>
                @endforeach
            </div>
        </div>
    </div>
</x-public-layout>
