<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- `inertia` on the title so a page can set its own through <Head>
             without the server and the client fighting over it. --}}
        <title inertia>{{ config('app.name', 'First to Act Poker') }}</title>

        <link rel="preload" href="{{ asset('fonts/archivo.woff2') }}" as="font" type="font/woff2" crossorigin>

        {{-- Before Vite, and deliberately inline. It stamps data-theme from
             localStorage before first paint; moving it into Vue would
             reintroduce the flash of the wrong theme it exists to prevent. --}}
        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>

        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
