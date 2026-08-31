<x-topbar>
    <x-slot name="links">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link--current' : '' }}"
           @if (request()->routeIs('dashboard')) aria-current="page" @endif
           href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>

        @if (Auth::user()->is_admin)
            <x-dropdown align="left" width="48" :inline-mobile="true">
                <x-slot name="trigger">
                    <button type="button"
                            class="nav-link {{ request()->routeIs('poker.seasons.*', 'poker.venues.*', 'poker.tournaments.*') ? 'nav-link--current' : '' }}"
                            @if (request()->routeIs('poker.seasons.*', 'poker.venues.*', 'poker.tournaments.*')) aria-current="page" @endif>
                        {{ __('League') }}
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('poker.seasons.index')">{{ __('Seasons') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('poker.venues.index')">{{ __('Venues') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('poker.tournaments.index')">{{ __('Tournaments') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>

            <x-dropdown align="left" width="48" :inline-mobile="true">
                <x-slot name="trigger">
                    <button type="button"
                            class="nav-link {{ request()->routeIs('poker.results.*', 'poker.registrants.*', 'poker.venue-points.*') ? 'nav-link--current' : '' }}"
                            @if (request()->routeIs('poker.results.*', 'poker.registrants.*', 'poker.venue-points.*')) aria-current="page" @endif>
                        {{ __('Play') }}
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('poker.results.index')">{{ __('Results') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('poker.registrants.index')">{{ __('Registrants') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('poker.venue-points.index')">{{ __('Venue points') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>

            <x-dropdown align="left" width="48" :inline-mobile="true">
                <x-slot name="trigger">
                    <button type="button"
                            class="nav-link {{ request()->routeIs('poker.points-structure.*', 'users.*') ? 'nav-link--current' : '' }}"
                            @if (request()->routeIs('poker.points-structure.*', 'users.*')) aria-current="page" @endif>
                        {{ __('Setup') }}
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('poker.points-structure.index')">{{ __('Points structure') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('users.index')">{{ __('Players') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>
        @endif
    </x-slot>

    <x-slot name="actions">
        {{-- Two structures rather than one restyled: on desktop the identity is a
             dropdown; on mobile it is a flat row. They differ in shape, not just
             in layout, so CSS alone cannot express both from one markup tree. --}}
        <div class="topbar__actions-desktop">
            <x-theme-toggle />

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" class="nav-link nav-link--user">
                        <x-avatar :user="auth()->user()" size="sm" decorative />
                        <span>{{ auth()->user()->display_name }}</span>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">{{ __('Your profile') }}</x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>

        {{-- Mobile: log out sits alone on the left where a destructive action is
             hard to hit by accident; theme and identity group on the right. The
             avatar and name go straight to the profile — no second disclosure. --}}
        <div class="topbar__actions-mobile">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-btn variant="danger" size="sm">{{ __('Log out') }}</x-btn>
            </form>

            <div class="topbar__identity">
                <x-theme-toggle />

                <a class="nav-link nav-link--user" href="{{ route('profile.edit') }}">
                    <x-avatar :user="auth()->user()" size="sm" decorative />
                    <span>{{ auth()->user()->display_name }}</span>
                </a>
            </div>
        </div>
    </x-slot>
</x-topbar>
