@props(['title' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card__header">
            <h2 class="card__title">{{ $title }}</h2>
            @isset($actions)
                <div class="l-cluster">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="card__body{{ $flush ? ' card__body--flush' : '' }}">{{ $slot }}</div>
</div>
