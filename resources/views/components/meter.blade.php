{{--
    role="meter" requires an accessible name per the ARIA spec (axe-core: aria-meter-name).
    Always pass a `label` describing what this specific meter shows, e.g.
    label="Points for Mara Vasquez". The default below is a generic fallback so the
    component never ships with no accessible name at all, but it is not a substitute
    for a real, specific label at each call site.
--}}
{{-- decimals: whole numbers by default, because almost every meter here
     measures points. A meter showing an AVERAGE passes 1 -- rounding 218.3
     and 218.4 to the same 218 hides the difference the standings are
     ordered on. --}}
{{-- valueText: what the value READS as, when that is not the number the bar
     measures. The standings in rank order show a position -- "#2" -- over a
     bar whose length is still the average behind it, with valueTitle carrying
     the figure itself. --}}
@props([
    'value' => 0,
    'max' => 0,
    'showValue' => true,
    'label' => null,
    'decimals' => 0,
    'valueText' => null,
    'valueTitle' => null,
])

@php
    $percentage = $max > 0 ? min(100, round(($value / $max) * 100, 2)) : 0;
    $accessibleLabel = $label ?? 'Progress: '.number_format($value, $decimals).' of '.number_format($max, $decimals);
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
        <span class="meter__value" @if ($valueTitle) title="{{ $valueTitle }}" @endif>
            {{ $valueText ?? number_format($value, $decimals) }}
        </span>
    @endif
</div>
