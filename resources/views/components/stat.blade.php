@props(['label', 'value'])

<div {{ $attributes->merge(['class' => 'stat']) }}>
    <span class="stat__label">{{ $label }}</span>
    <span class="stat__value">{{ $value }}</span>
</div>
