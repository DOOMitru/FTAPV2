<x-public-layout>
    <div class="p-split">
        <x-card :title="__('Reset your password')" class="p-raised">
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
