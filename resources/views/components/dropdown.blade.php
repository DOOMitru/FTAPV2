@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700', 'up' => false])

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

// `.dropdown` / `.dropdown__menu` encode exactly the shape every call site
// in the app uses today: right/end-aligned, width `48`, default
// contentClasses. That's the only combination exercised anywhere, so it's
// the only one mapped to design-system classes here. Any other
// align/width/contentClasses value keeps its original Tailwind below and
// can be converted once a real consumer needs it.
$usesDefaultShape = $align === 'right'
    && $width === '48'
    && $contentClasses === 'py-1 bg-white dark:bg-gray-700';
@endphp

<div class="dropdown" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open" aria-haspopup="menu" x-bind:aria-expanded="open.toString()">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="{{ $usesDefaultShape ? 'dropdown__menu'.($up ? ' dropdown__menu--up' : '') : 'absolute z-50 mt-2 '.$widthClasses.' rounded-md shadow-lg '.$alignmentClasses }}"
            x-cloak
            @click="open = false">
        <div class="{{ $usesDefaultShape ? '' : 'rounded-md ring-1 ring-black ring-opacity-5 '.$contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
