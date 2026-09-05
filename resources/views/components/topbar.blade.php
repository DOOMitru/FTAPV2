@props(['links' => null, 'actions' => null])

{{-- Shared between the authenticated and public shells (Task 9 consumes this
     same component for the public site) so the mobile-menu logic exists
     exactly once instead of drifting between two hand-copied layouts. --}}
{{-- click.outside lives on the header, NOT on the panel. The burger sits outside
     the panel, so a click.outside bound to the panel fires on the very click that
     opens it: the button sets menuOpen = true, the document listener then sets it
     false, and the menu can never stay open. Scoping it to the header — which
     contains both the burger and the panel — means only a click elsewhere on the
     page closes it. --}}
<header class="topbar" x-data="{ menuOpen: false }"
        x-on:keydown.escape.window="menuOpen = false"
        x-on:click.outside="menuOpen = false">
    <div class="topbar__inner">
        <x-brand />

        <button type="button" class="topbar__burger" x-on:click="menuOpen = ! menuOpen"
                x-bind:aria-expanded="menuOpen.toString()" aria-controls="topbar-menu">
            <svg class="topbar__burger-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" aria-hidden="true">
                <path x-show="! menuOpen" d="M4 7h16M4 12h16M4 17h16"/>
                <path x-show="menuOpen" x-cloak d="M6 6l12 12M18 6L6 18"/>
            </svg>
            <span class="u-visually-hidden">{{ __('Menu') }}</span>
        </button>

        <div class="topbar__panel" id="topbar-menu"
             x-bind:class="{ 'topbar__panel--open': menuOpen }">
            <nav class="topbar__nav" aria-label="{{ __('Main') }}">{{ $links }}</nav>
            <div class="topbar__actions">{{ $actions }}</div>
        </div>
    </div>
</header>
