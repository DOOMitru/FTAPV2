@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
// Every size is a modifier now, so the prop is honoured rather than quietly
// ignored for anything but the default.
$panelClasses = 'modal__panel modal__panel--'.($maxWidth ?: '2xl');
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-effect="
        if (show) {
            document.body.classList.add('is-modal-open');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('is-modal-open');
        }
    "
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    x-cloak
    class="modal"
>
    <div
        x-show="show"
        class="modal__overlay"
        x-on:click="show = false"
        x-transition:enter="modal__motion"
        x-transition:enter-start="modal__fade-from"
        x-transition:enter-end="modal__fade-to"
        x-transition:leave="modal__motion modal__motion--leaving"
        x-transition:leave-start="modal__fade-to"
        x-transition:leave-end="modal__fade-from"
    >
        <div class="modal__backdrop"></div>
    </div>

    <div
        x-show="show"
        class="{{ $panelClasses }}"
        x-transition:enter="modal__motion"
        x-transition:enter-start="modal__rise-from"
        x-transition:enter-end="modal__rise-to"
        x-transition:leave="modal__motion modal__motion--leaving"
        x-transition:leave-start="modal__rise-to"
        x-transition:leave-end="modal__rise-from"
    >
        {{ $slot }}
    </div>
</div>
