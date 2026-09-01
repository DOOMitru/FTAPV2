@props(['type' => 'scheduled', 'label' => null])

@php
    $isChampionship = $type === 'championship';
    $badgeLabel = $label ?? ($isChampionship ? __('Championship') : __('Scheduled'));
@endphp

<div {{ $attributes->merge(['class' => 'token'.($isChampionship ? ' token--championship' : '')]) }}>
    <span class="token__ring" aria-hidden="true"></span>

    {{-- The eight rim marks. Their angles became static classes in Phase 1
         Task 11, when the inline rotate() transforms came out. --}}
    <span class="token__marks" aria-hidden="true">
        @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $angle)
            <span class="chip-mark chip-mark--{{ $angle }}"></span>
        @endforeach
    </span>

    <span class="token__body">
        @if ($isChampionship)
            <svg class="token__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5M19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z"/>
            </svg>
        @else
            <svg class="token__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @endif

        <span class="token__label">{{ $badgeLabel }}</span>
    </span>
</div>
