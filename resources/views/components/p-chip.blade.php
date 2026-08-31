@props(['index' => null])

{{-- A small centred tile in a grid: hand rankings, point tiers. --}}
<div {{ $attributes->merge(['class' => 'p-chip']) }}>
    @if ($index !== null)
        <span class="p-chip__index">{{ $index }}</span>
    @endif

    <span class="p-chip__label">{{ $slot }}</span>
</div>
