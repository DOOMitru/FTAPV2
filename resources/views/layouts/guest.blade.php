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
        <div class="guest">
            <div class="guest__panel l-stack">
                <div class="guest__brand"><x-brand /></div>
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
