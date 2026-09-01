<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'First to Act Poker') }}</title>

        <link rel="preload" href="{{ asset('fonts/archivo.woff2') }}" as="font" type="font/woff2" crossorigin>

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>
    </head>
    <body>
        <div class="shell">
            @include('layouts.navigation')

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
            @isset($header)
                <header class="shell__header">
                    <div class="shell__header-inner">{{ $header }}</div>
                </header>
            @endisset

            {{-- No .l-container here. Every view supplies its own, and this
                 one nested inside it -- .l-container sets padding-inline, so
                 two of them meant the gutter was applied twice: 32px a side on
                 a phone, where the content had the least room to give. --}}
            <main class="shell__content">{{ $slot }}</main>

            {{-- .shell is a flex column with min-height: 100vh and
                 .shell__content takes flex: 1, so this sits at the bottom of
                 the viewport on a short page rather than floating mid-screen. --}}
            <x-site-footer />
        </div>
    </body>
</html>
