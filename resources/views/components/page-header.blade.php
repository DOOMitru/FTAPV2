@props(['title', 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'l-cluster l-cluster--between']) }}>
    <div>
        @if ($eyebrow)
            <p class="u-eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="page-header__title">{{ $title }}</h1>
    </div>

    @isset($actions)
        <div class="l-cluster">{{ $actions }}</div>
    @endisset
</div>
