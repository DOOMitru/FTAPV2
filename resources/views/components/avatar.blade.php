{{--
    `decorative` should be passed true whenever this avatar sits next to an
    already-visible name (e.g. a leaderboard row), so the name isn't announced
    twice by a screen reader. Left false (the default), the avatar renders the
    display-name alt, since a standalone avatar does need a name.
--}}
@props(['user', 'size' => 'md', 'decorative' => false])

@php
    $dimension = $size === 'lg' ? 64 : 40;
@endphp

<img
    {{ $attributes->merge([
        'class' => 'avatar'.($size === 'lg' ? ' avatar--lg' : ''),
        'src' => $user->profile_image_url,
        'alt' => $decorative ? '' : $user->display_name,
        'width' => $dimension,
        'height' => $dimension,
    ]) }}
>
