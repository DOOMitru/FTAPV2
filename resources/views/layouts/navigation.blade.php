<x-topbar>
    <x-slot name="leading">
        {{-- Mobile only: on desktop the notifications live in the user menu,
             and two surfaces showing the same list at once is one too many.
             .topbar__bell-menu is hidden above the 48rem breakpoint the rest of
             the topbar already splits on. --}}
        {{-- Deliberately NOT dropdown--notifications: that class fixes the
             panel at 22rem, and this one is sized by .topbar__bell-menu to span
             the bar. Both selectors are specificity 0,2,0 and _dropdown.css
             loads after _topbar.css, so carrying both let the fixed width win
             and pushed the panel off the screen. --}}
        <x-dropdown align="right" class="topbar__bell-menu">
            <x-slot name="trigger">
                <button type="button" class="topbar__bell">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>

                    <span class="u-visually-hidden">{{ __('Notifications') }}</span>

                    @if ($unreadNotificationCount > 0)
                        <span class="topbar__bell-badge">
                            {{ $unreadNotificationCount }}
                            <span class="u-visually-hidden">{{ __('unread notifications') }}</span>
                        </span>
                    @endif
                </button>
            </x-slot>

            <x-slot name="content">
                <p class="dropdown__heading">{{ __('Notifications') }}</p>

                <div class="notification-list">
                    @forelse ($recentNotifications as $notification)
                        <x-notification-card :notification="$notification" />
                    @empty
                        <p class="dropdown__empty">{{ __('No notifications yet.') }}</p>
                    @endforelse
                </div>

                @if ($recentNotifications->whereNotNull('read_at')->isNotEmpty())
                    <form action="{{ route('notifications.clear-read') }}" method="POST"
                          data-confirm="{{ __('Delete every notification you have already read?') }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="dropdown__item">{{ __('Clear read notifications') }}</button>
                    </form>
                @endif
            </x-slot>
        </x-dropdown>
    </x-slot>

    <x-slot name="links">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link--current' : '' }}"
           @if (request()->routeIs('dashboard')) aria-current="page" @endif
           href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>

        {{-- League is open to everyone signed in: the three lists behind it are
             records a player has a reason to read. Play and Setup below stay
             behind the admin gate -- they are for running the league, not
             following it. --}}
        <x-dropdown align="left" :inline-mobile="true">
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

        @if (Auth::user()->is_admin)

            <x-dropdown align="left" :inline-mobile="true">
                <x-slot name="trigger">
                    <button type="button"
                            class="nav-link {{ request()->routeIs('poker.registrants.*', 'poker.venue-points.*') ? 'nav-link--current' : '' }}"
                            @if (request()->routeIs('poker.registrants.*', 'poker.venue-points.*')) aria-current="page" @endif>
                        {{ __('Play') }}
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('poker.registrants.index')">{{ __('Registrants') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('poker.venue-points.index')">{{ __('Venue points') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>

            <x-dropdown align="left" :inline-mobile="true">
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
                    <x-dropdown-link :href="route('sponsors.index')">{{ __('Sponsors') }}</x-dropdown-link>
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

            {{-- Two regions now: the notifications a player came to read, and
                 the account links the menu already held. --}}
            <x-dropdown align="right" class="dropdown--notifications">
                <x-slot name="trigger">
                    <button type="button" class="nav-link nav-link--user">
                        <x-monogram :user="auth()->user()" size="sm" decorative />
                        <span>{{ auth()->user()->display_name }}</span>

                        {{-- Absent at zero. A badge reading 0 is a badge saying
                             nothing, loudly. --}}
                        @if ($unreadNotificationCount > 0)
                            <span class="nav-link__badge">
                                {{ $unreadNotificationCount }}
                                <span class="u-visually-hidden">{{ __('unread notifications') }}</span>
                            </span>
                        @endif
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="dropdown__section">
                        <p class="dropdown__heading">{{ __('Notifications') }}</p>

                        <div class="notification-list">
                            @forelse ($recentNotifications as $notification)
                                <x-notification-card :notification="$notification" />
                            @empty
                                <p class="dropdown__empty">{{ __('No notifications yet.') }}</p>
                            @endforelse
                        </div>

                        @if ($recentNotifications->whereNotNull('read_at')->isNotEmpty())
                            <form action="{{ route('notifications.clear-read') }}" method="POST"
                                  data-confirm="{{ __('Delete every notification you have already read?') }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="dropdown__item">{{ __('Clear read notifications') }}</button>
                            </form>
                        @endif
                    </div>

                    <div class="dropdown__section">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Your profile') }}</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown__item">{{ __('Log out') }}</button>
                        </form>
                    </div>
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
                    <x-monogram :user="auth()->user()" size="sm" decorative />
                    <span>{{ auth()->user()->display_name }}</span>
                </a>
            </div>
        </div>
    </x-slot>
</x-topbar>
