@props(['eyebrow' => null, 'title', 'highlight' => null, 'align' => 'center', 'level' => 1, 'plain' => false])

{{--
    The opening block on every public page. `highlight` is the one word of the
    title that takes the accent — a prop rather than a <span> the caller styles,
    so eight pages cannot end up with eight different accent colours. It is
    matched inside the title and wrapped; if it is absent from the title the
    title renders whole, so a typo degrades quietly rather than dropping words.
--}}
@php
    $before = $title;
    $after = null;

    if (filled($highlight) && str_contains($title, $highlight)) {
        [$before, $after] = explode($highlight, $title, 2);
    }
@endphp

@php
    $heading = 'h'.($level === 1 ? '1' : '2');
    $classes = 'p-hero'
        .($align === 'start' ? ' p-hero--start' : '')
        .($plain ? ' p-hero--plain' : '');
@endphp

<header {{ $attributes->merge(['class' => $classes]) }}>
    @if ($eyebrow)
        <p class="u-eyebrow p-hero__eyebrow">{{ $eyebrow }}</p>
    @endif

    <{{ $heading }} class="p-hero__title">
        {{ $before }}@if ($after !== null)<span class="p-hero__highlight">{{ $highlight }}</span>{{ $after }}@endif
    </{{ $heading }}>

    @if (trim($slot) !== '')
        <p class="p-hero__lede">{{ $slot }}</p>
    @endif
</header>
