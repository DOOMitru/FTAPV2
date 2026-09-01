@props(['eyebrow' => null, 'title', 'highlight' => null, 'align' => 'center', 'level' => 1, 'plain' => false, 'suit' => 'spade'])

{{--
    The opening block on every public page. `highlight` is the one word of the
    title that takes the accent — a prop rather than a <span> the caller styles,
    so eight pages cannot end up with eight different accent colours. It is
    matched inside the title and wrapped; if it is absent from the title the
    title renders whole, so a typo degrades quietly rather than dropping words.

    `suit` picks the card suit that marks the eyebrow and watermarks the band.
    It is ornament, not information — both renderings are aria-hidden, and a
    suit here deliberately encodes nothing. (Suits are NOT used beside table
    rows for exactly that reason: there is no tournament-type column, so one
    there would imply a category that does not exist.)
--}}
@php
    $before = $title;
    $after = null;

    if (filled($highlight) && str_contains($title, $highlight)) {
        [$before, $after] = explode($highlight, $title, 2);
    }

    $heading = 'h'.($level === 1 ? '1' : '2');
    $classes = 'p-hero'
        .($align === 'start' ? ' p-hero--start' : '')
        .($plain ? ' p-hero--plain' : '');

    // The glyphs, as characters rather than HTML entities: Blade escapes on
    // output, so an entity written here would render literally — a bug this
    // project has already shipped once with &amp;.
    $glyphs = ['spade' => '♠', 'heart' => '♥', 'diamond' => '♦', 'club' => '♣'];
    $glyph = $glyphs[$suit] ?? $glyphs['spade'];
@endphp

<header {{ $attributes->merge(['class' => $classes]) }}>
    {{-- A band-scale watermark, and only on a band: .p-hero--plain strips the
         padding and background, so a watermark there would float against the
         page with nothing to sit inside. --}}
    @unless ($plain)
        <span class="p-hero__watermark" aria-hidden="true">{{ $glyph }}</span>
    @endunless

    @if ($eyebrow)
        <p class="u-eyebrow p-hero__eyebrow">
            <span class="p-hero__suit" aria-hidden="true">{{ $glyph }}</span>{{ $eyebrow }}
        </p>
    @endif

    <{{ $heading }} class="p-hero__title">
        {{ $before }}@if ($after !== null)<span class="p-hero__highlight">{{ $highlight }}</span>{{ $after }}@endif
    </{{ $heading }}>

    @if (trim($slot) !== '')
        <p class="p-hero__lede">{{ $slot }}</p>
    @endif
</header>
