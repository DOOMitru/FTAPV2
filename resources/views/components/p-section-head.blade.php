@props(['title', 'icon' => null])

{{--
    An icon tile beside a heading. `icon` is an SVG path string, matching how
    the rules pages already carry their icons in data. It is rendered with
    {!! !!} because it is markup, and it is developer-authored data from an
    inline @php array — never user input.
--}}
<div {{ $attributes->merge(['class' => 'p-section-head']) }}>
    @if ($icon)
        <span class="p-section-head__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="{!! $icon !!}"/>
            </svg>
        </span>
    @endif

    <h2 class="p-section-head__title">{{ $title }}</h2>
</div>
