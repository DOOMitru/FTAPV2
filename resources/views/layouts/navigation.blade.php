<nav class="shell__rail" x-ref="rail" x-bind:class="{ 'shell__rail--open': railOpen }" aria-label="{{ __('Main') }}">
    <a class="shell__brand" href="{{ route('dashboard') }}">{{ __('FTAP') }}</a>

    <div>
        <div class="nav-group">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link--current' : '' }}"
               @if (request()->routeIs('dashboard')) aria-current="page" @endif
               href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        </div>

        @if (Auth::user()->is_admin)
            <div class="nav-group">
                <span class="nav-group__label">{{ __('League') }}</span>
                <a class="nav-link {{ request()->routeIs('poker.seasons.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.seasons.*')) aria-current="page" @endif
                   href="{{ route('poker.seasons.index') }}">{{ __('Seasons') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.venues.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.venues.*')) aria-current="page" @endif
                   href="{{ route('poker.venues.index') }}">{{ __('Venues') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.tournaments.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.tournaments.*')) aria-current="page" @endif
                   href="{{ route('poker.tournaments.index') }}">{{ __('Tournaments') }}</a>
            </div>

            <div class="nav-group">
                <span class="nav-group__label">{{ __('Play') }}</span>
                <a class="nav-link {{ request()->routeIs('poker.results.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.results.*')) aria-current="page" @endif
                   href="{{ route('poker.results.index') }}">{{ __('Results') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.registrants.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.registrants.*')) aria-current="page" @endif
                   href="{{ route('poker.registrants.index') }}">{{ __('Registrants') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.venue-points.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.venue-points.*')) aria-current="page" @endif
                   href="{{ route('poker.venue-points.index') }}">{{ __('Venue points') }}</a>
            </div>

            <div class="nav-group">
                <span class="nav-group__label">{{ __('Setup') }}</span>
                <a class="nav-link {{ request()->routeIs('poker.points-structure.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('poker.points-structure.*')) aria-current="page" @endif
                   href="{{ route('poker.points-structure.index') }}">{{ __('Points structure') }}</a>
                <a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link--current' : '' }}"
                   @if (request()->routeIs('users.*')) aria-current="page" @endif
                   href="{{ route('users.index') }}">{{ __('Players') }}</a>
            </div>
        @endif
    </div>

    <div class="shell__rail-footer l-stack l-stack--tight">
        <button type="button" class="nav-link" data-theme-toggle aria-pressed="false">
            {{ __('Switch theme') }}
        </button>

        {{-- A real user menu, per spec §6.5's "theme toggle · user menu". This is the app's
             only remaining <x-dropdown> consumer once the old top-bar navigation goes, so
             removing it would strand the component, its stylesheet, and Task 12's
             dropdown-link work. It is also simply better than three flat links in a rail.
             `up`: the trigger is pinned to the rail's bottom (margin-block-start: auto), so
             the default downward menu opens off the bottom of the viewport on mobile — see
             .dropdown__menu--up in _dropdown.css. --}}
        <x-dropdown align="right" width="48" up>
            <x-slot name="trigger">
                <button type="button" class="nav-link nav-link--user">
                    <x-avatar :user="auth()->user()" decorative />
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
</nav>
