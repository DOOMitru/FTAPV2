<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'First To Act Poker') }}</title>

        <link rel="preload" href="{{ asset('fonts/archivo.woff2') }}" as="font" type="font/woff2" crossorigin>

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>
    </head>
    <body>
        <div class="shell"
             x-data="{
                railOpen: false,
                openRail() {
                    this.railOpen = true;
                    this.$nextTick(() => this.$refs.rail.querySelector('a, button')?.focus());
                },
                closeRail() {
                    if (! this.railOpen) return;
                    this.railOpen = false;
                    this.$refs.drawerToggle.focus();
                },
             }"
             x-on:keydown.escape.window="closeRail()">
            @include('layouts.navigation')

            {{-- Below 56.25rem only (see .shell__backdrop in _shell-app.css): dims the
                 page while the drawer is open and gives mouse/touch users a
                 click-outside target, since the fixed-position rail has no
                 scrollable ancestor to supply one on its own. --}}
            <div class="shell__backdrop" x-show="railOpen" x-cloak x-on:click="closeRail()"></div>

            <div class="shell__main">
                <button type="button" class="btn btn--ghost btn--sm shell__drawer-toggle"
                        x-ref="drawerToggle"
                        x-on:click="railOpen ? closeRail() : openRail()"
                        x-bind:aria-expanded="railOpen.toString()">
                    {{ __('Menu') }}
                </button>

                @isset($header)
                    <header class="shell__header"><div class="shell__header-inner">{{ $header }}</div></header>
                @endisset

                {{-- Flash messages deliberately not rendered here yet: eight views already
                     render session('status')/session('error') themselves (poker/{seasons,
                     tournaments,points-structure,venues,registrants,venue-points,results}/index
                     and poker/tournaments/show), so a layout-level block would duplicate them.
                     Worse, ProfileController, Auth/PasswordController and
                     Auth/EmailVerificationNotificationController flash sentinel strings
                     ('profile-updated', 'password-updated', 'verification-link-sent') that the
                     profile partials match on but never display — a naive block here would
                     render those literally. The layout takes ownership of flash messages once
                     the views that duplicate them convert and the sentinel-flashing controllers
                     are updated to flash real copy; until then this is the same YAGNI call
                     already made for pagination in this phase. --}}
                <main class="shell__content">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
