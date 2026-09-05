@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => null,
])

@php
    // ghost is the Cancel/dismiss variant, mirroring the legacy
    // secondary-button component it replaces: it must not submit a form by
    // accident, so it defaults to type="button" while every other variant
    // (primary/danger, the "do the thing" buttons) keeps type="submit". An
    // explicit type prop from the caller always wins.
    $type = $type ?? ($variant === 'ghost' ? 'button' : 'submit');
    $classes = 'btn btn--'.$variant.($size === 'sm' ? ' btn--sm' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
