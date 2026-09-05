@props(['title'])

<div {{ $attributes->merge(['class' => 'empty']) }}>
    <p class="empty__title">{{ $title }}</p>

    @if (trim($slot) !== '')
        <p class="empty__body">{{ $slot }}</p>
    @endif

    @isset($action)
        <div class="empty__action">{{ $action }}</div>
    @endisset
</div>
