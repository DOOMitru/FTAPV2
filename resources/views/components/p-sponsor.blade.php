@props(['sponsor'])

{{-- One sponsor on the public wall.
     Extracted because the wall draws two grids now -- premium and regular --
     and the same card in two loops is the same card until somebody edits one
     of them. --}}
@php
    $classes = 'p-sponsor p-raised p-lift'.($sponsor->isPremium() ? ' p-sponsor--premium' : '');
@endphp

{{-- The card is a link only when there is somewhere to go. An <a> without an
     href is not a link, and a card that looks clickable and is not is worse
     than a plain one. --}}
@if ($sponsor->website_url)
    <a class="{{ $classes }}" href="{{ $sponsor->website_url }}"
       target="_blank" rel="noopener noreferrer">
        {{-- alt is the sponsor's NAME, not empty: the logo is the content
             here, and empty alt would leave a screen reader with nothing where
             a sponsor should be. --}}
        <img class="p-sponsor__logo" src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}">
        <span class="p-sponsor__name">{{ $sponsor->name }}</span>
        <span class="u-visually-hidden">{{ __('(opens in a new tab)') }}</span>
    </a>
@else
    <div class="{{ $classes }}">
        <img class="p-sponsor__logo" src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}">
        <span class="p-sponsor__name">{{ $sponsor->name }}</span>
    </div>
@endif
