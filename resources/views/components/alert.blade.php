@props(['variant' => 'info'])

<div {{ $attributes->merge(['class' => 'alert alert--'.$variant]) }} role="{{ $variant === 'danger' ? 'alert' : 'status' }}">
    {{ $slot }}
</div>
