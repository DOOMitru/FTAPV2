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
        <div class="public">
            <x-topbar>
                <x-slot name="links">
                    <a class="nav-link {{ request()->routeIs('home') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('home')) aria-current="page" @endif
                       href="{{ route('home') }}">{{ __('Home') }}</a>

                    <a class="nav-link {{ request()->routeIs('events') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('events')) aria-current="page" @endif
                       href="{{ route('events') }}">{{ __('Events') }}</a>

                    <x-dropdown align="left" :inline-mobile="true">
                        <x-slot name="trigger">
                            <button type="button"
                                    class="nav-link {{ request()->routeIs('rules.*') ? 'nav-link--current' : '' }}"
                                    @if (request()->routeIs('rules.*')) aria-current="page" @endif>
                                {{ __('Rules') }}
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('rules.tournament')">{{ __('Regulations') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('rules.betting')">{{ __('Conduct') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('rules.texas-holdem')">{{ __('How to play') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('rules.points-structure')">{{ __('Points') }}</x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <a class="nav-link {{ request()->routeIs('about.*') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('about.*')) aria-current="page" @endif
                       href="{{ route('about.index') }}">{{ __('About') }}</a>

                    <a class="nav-link {{ request()->routeIs('contact') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('contact')) aria-current="page" @endif
                       href="{{ route('contact') }}">{{ __('Contact') }}</a>
                </x-slot>

                <x-slot name="actions">
                    @auth
                        {{-- Signed in, the public bar carries the same two structures as
                             the dashboard's own bar and reuses its classes: a plain row on
                             desktop, a flat left/right row on mobile. Dashboard is a nav
                             link, not a button -- it is another place to go, the same as
                             Events or About. --}}
                        <div class="topbar__actions-desktop">
                            <x-theme-toggle />
                            <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                        </div>

                        {{-- Mobile: log out alone on the left where a destructive action is
                             hard to hit by accident; theme and Dashboard group on the right,
                             matching layouts/navigation.blade.php exactly. --}}
                        <div class="topbar__actions-mobile">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-btn variant="danger" size="sm">{{ __('Log out') }}</x-btn>
                            </form>

                            <div class="topbar__identity">
                                <x-theme-toggle />
                                <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                            </div>
                        </div>
                    @else
                        <x-theme-toggle />
                        <a class="nav-link" href="{{ route('login') }}">{{ __('Log in') }}</a>
                        {{-- "Join" stays a button: it is the one call to action on the page. --}}
                        <x-btn variant="primary" size="sm" :href="route('register')">{{ __('Join') }}</x-btn>
                    @endauth
                </x-slot>
            </x-topbar>

            <main class="public__main">
                <div class="l-container l-stack">{{ $slot }}</div>
            </main>

            <footer class="public__footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </footer>
        </div>
    </body>
</html>
