@props([
    'icon',
    'label',
    'href' => null,
    'danger' => false,
])

{{--
    One row action in a dashboard table, as an icon.

    The label is not decoration: an icon-only control has no accessible name of
    its own, so every one of these carries the word in a visually-hidden span --
    from content, not aria-label, so it is translated with the rest of the page
    -- and repeats it in title for a sighted mouse user.

    Rendered as an <a> when given an href and as a submit button otherwise. The
    surrounding <form>, its @csrf/@method and its data-confirm stay at the call
    site, because those differ per action and this component has no business
    knowing them.
--}}
@php
    $classes = 'action'.($danger ? ' action--danger' : '');
@endphp

<{{ $href ? 'a' : 'button' }}
    @if ($href) href="{{ $href }}" @else type="submit" @endif
    class="{{ $classes }}"
    title="{{ $label }}">

    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch ($icon)
            @case('view')
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
                @break

            @case('stats')
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6" y1="20" x2="6" y2="14"/>
                @break

            @case('edit')
                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
                @break

            @case('delete')
                <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                <line x1="10" y1="11" x2="10" y2="17"/>
                <line x1="14" y1="11" x2="14" y2="17"/>
                @break

            @case('approve')
                <path d="M20 6L9 17l-5-5"/>
                @break

            @case('reject')
                <path d="M18 6L6 18M6 6l12 12"/>
                @break
        @endswitch
    </svg>

    <span class="u-visually-hidden">{{ $label }}</span>
</{{ $href ? 'a' : 'button' }}>
