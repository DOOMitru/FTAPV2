@props(['number' => null, 'title'])

{{--
    A numbered rule. 21 of these on the Texas Hold'em page alone. The number is
    the citation key, so it sits in --font-mono at a fixed width and the titles
    line up down the column regardless of how many digits it carries.
--}}
<div {{ $attributes->merge(['class' => 'p-item p-raised p-lift']) }}>
    @if ($number !== null)
        <span class="p-item__number">{{ $number }}</span>
    @endif

    <div class="p-item__body">
        <h3 class="p-item__title">{{ $title }}</h3>
        <p class="p-item__text">{{ $slot }}</p>
    </div>
</div>
