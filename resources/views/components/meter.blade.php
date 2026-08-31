{{--
    role="meter" requires an accessible name per the ARIA spec (axe-core: aria-meter-name).
    Always pass a `label` describing what this specific meter shows, e.g.
    label="Points for Mara Vasquez". The default below is a generic fallback so the
    component never ships with no accessible name at all, but it is not a substitute
    for a real, specific label at each call site.
--}}
@props(['value' => 0, 'max' => 0, 'showValue' => true, 'label' => null])

@php
    $percentage = $max > 0 ? min(100, round(($value / $max) * 100, 2)) : 0;
    $accessibleLabel = $label ?? 'Progress: '.number_format($value).' of '.number_format($max);
@endphp

{{--
    `style` is deliberately kept as a literal attribute rather than folded into
    merge(), even though role/aria-value*/label below are. It carries the one
    approved custom-property escape hatch (--meter-fill) for the no-inline-CSS
    rule; running it through merge() risks it being combined with a caller-supplied
    style and defeating that rule. Keep it separate.
--}}
<div
    {{ $attributes->merge([
        'class' => 'meter',
        'role' => 'meter',
        'aria-valuenow' => $value,
        'aria-valuemin' => 0,
        'aria-valuemax' => $max,
        'aria-label' => $accessibleLabel,
    ]) }}
    style="--meter-fill: {{ $percentage }}%"
>
    <div class="meter__track"><div class="meter__fill"></div></div>

    @if ($showValue)
        <span class="meter__value">{{ number_format($value) }}</span>
    @endif
</div>
