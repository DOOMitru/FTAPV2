{{-- Links to the public site from both bars: the mark is the way back out of
     the admin area, not a link to the dashboard. --}}
<a class="brand" href="{{ route('home') }}">
    <img class="brand__logo" src="{{ asset('images/header_logo.png') }}" alt="">
    <span class="brand__text">
        <span class="brand__name">{{ __('First to Act Poker') }}</span>
        <span class="brand__location">{{ __('Regina, SK') }}</span>
    </span>
</a>
