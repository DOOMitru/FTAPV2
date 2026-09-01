@props(['place'])

@php
    // Places 1-3 carry a medal; everything below is the quiet default. The
    // modifier is per-place rather than one shared --podium, because a podium
    // whose three seats are the same colour states no order.
    $medal = $place >= 1 && $place <= 3 ? ' rank--'.$place : '';
@endphp

<span {{ $attributes->merge(['class' => 'rank'.$medal]) }}>{{ $place }}</span>
