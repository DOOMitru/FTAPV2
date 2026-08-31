@props(['align' => 'right', 'inlineMobile' => false])

@php
// inlineMobile makes the panel flow in the document below 48rem instead of
// floating over it — an absolutely-positioned popup inside a stacked mobile
// menu reads as a detached overlay rather than a disclosure.
$menuClasses = 'dropdown__menu'
    .($align === 'left' ? ' dropdown__menu--left' : '')
    .($inlineMobile ? ' dropdown__menu--inline-mobile' : '');
@endphp

<div {{ $attributes->merge(['class' => 'dropdown']) }} x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div class="dropdown__trigger" @click="open = ! open" aria-haspopup="menu" x-bind:aria-expanded="open.toString()">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="dropdown__motion"
            x-transition:enter-start="dropdown__motion-from"
            x-transition:enter-end="dropdown__motion-to"
            x-transition:leave="dropdown__motion dropdown__motion--leaving"
            x-transition:leave-start="dropdown__motion-to"
            x-transition:leave-end="dropdown__motion-from"
            class="{{ $menuClasses }}"
            x-cloak
            @click="open = false">
        {{ $content }}
    </div>
</div>
