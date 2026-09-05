{{--
    `decorative` should be passed true whenever this avatar sits next to an
    already-visible name (e.g. a leaderboard row), so the name isn't announced
    twice by a screen reader. Left false (the default), the avatar renders the
    display-name alt, since a standalone avatar does need a name.
--}}
@props(['user', 'size' => 'md', 'decorative' => false])

@php
    $dimension = match ($size) {
        'lg' => 64,
        'sm' => 24,
        default => 40,
    };

    $sizeClass = match ($size) {
        'lg' => ' avatar--lg',
        'sm' => ' avatar--sm',
        default => '',
    };
@endphp

<img
    {{ $attributes->merge([
        'class' => 'avatar'.$sizeClass,
        'src' => $user->profile_image_url,
        'alt' => $decorative ? '' : $user->display_name,
        'width' => $dimension,
        'height' => $dimension,
    ]) }}
>
