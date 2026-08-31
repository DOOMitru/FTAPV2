@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700', 'inlineMobile' => false])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$widthClasses = match ($width) {
    '48' => 'w-48',
    default => $width,
};

// The design-system shape covers both alignments the app actually uses --
// `right` (the user menu) and `left` (the three admin group menus in the top
// bar) -- at the default width and contentClasses. Any other combination keeps
// its original Tailwind below and converts when a real consumer needs it.
$usesDefaultShape = in_array($align, ['right', 'left'], true)
    && $width === '48'
    && $contentClasses === 'py-1 bg-white dark:bg-gray-700';

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
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="{{ $usesDefaultShape ? $menuClasses : 'absolute z-50 mt-2 '.$widthClasses.' rounded-md shadow-lg '.$alignmentClasses }}"
            x-cloak
            @click="open = false">
        <div class="{{ $usesDefaultShape ? '' : 'rounded-md ring-1 ring-black ring-opacity-5 '.$contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
