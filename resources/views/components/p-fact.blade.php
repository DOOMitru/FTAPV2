@props(['label'])

{{--
    A label above a value, with a rail marking it. Used for the tiered lists
    inside a panel — penalty escalation, final-stakes terms.
--}}
<div {{ $attributes->merge(['class' => 'p-fact']) }}>
    <div>
        <span class="p-fact__label">{{ $label }}</span>
        <span class="p-fact__value">{{ $slot }}</span>
    </div>

    <span class="p-fact__rail" aria-hidden="true"></span>
</div>
