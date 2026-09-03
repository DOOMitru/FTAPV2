<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="$tournament->name">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="ghost" :href="route('poker.tournaments.index')">{{ __('Back') }}</x-btn>
                    <x-btn variant="primary" :href="route('poker.tournaments.edit', $tournament)">{{ __('Edit') }}</x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        {{-- The same card the events and home pages draw, so the three cannot
             drift. This was a bespoke <x-card flush> laid out with .l-sidebar,
             which handed the map two thirds of the width and left the details
             in the remaining third with no padding at all: the registration
             time sat hard against the card's edge and, on a past event, the
             podium was clipped off the bottom.

             No Details button -- this IS the details page. --}}
        <x-p-event :tournament="$tournament" :details="false">
            @if ($tournament->description)
                <p class="u-muted">{{ $tournament->description }}</p>
            @endif

            <x-slot name="actions">
                {{-- Unregister lives only here, so the shared card does not
                     carry it. It stands in the action row where Register would
                     be, so it is a button again rather than a menu item.

                     Ghost, not primary: leaving is not what the card is asking
                     you to do, and a red button alone in that row would read as
                     the call to action.

                     Both conditions are the controller's: it refuses an
                     unregister once registration has closed. --}}
                @if ($isUserRegistered && $tournament->registration_open)
                    <form action="{{ route('tournaments.unregister', $tournament) }}" method="POST"
                          data-confirm="{{ __('Are you sure you want to unregister from this tournament?') }}">
                        @csrf
                        @method('DELETE')
                        <x-btn variant="ghost" type="submit">{{ __('Unregister') }}</x-btn>
                    </form>
                @endif
            </x-slot>
        </x-p-event>

        {{-- No Registrants tile: the Registered Players card below carries the
             same count in its header badge, and a KPI that restates a number
             already on the page spends a quarter of the row saying nothing new.

             The whole row is conditional now. With that tile gone an upcoming
             tournament has no figures at all, and the grid would have rendered
             as an empty band above the panels. --}}
        @if ($isPast)
            <div class="l-grid l-grid--tight">
                <x-stat :label="__('Final Results')" :value="$resultsCount" />
                <x-stat :label="__('Avg Points')"
                        :value="$resultsCount ? number_format($totalPoints / $resultsCount) : '0'" />
                <x-stat :label="__('Points Pot')" :value="number_format($totalPoints)" />
            </div>
        @endif

        {{-- Below 60rem the two columns dissolve and these cards become
             items of one grid, ordered by .tshow__* rather than by which
             column they were written into. See 4-pages/_tournament-show.css. --}}
        <div class="l-sidebar tshow__panels">
            <div class="l-stack">
                {{-- No $isPast here. The podium is empty until its places are
                     settled, so it hides itself -- and "play has begun" was
                     never the right question anyway: what decides a podium is
                     how many players are left, not the clock. --}}
                @if ($podium->isNotEmpty())
                    <x-card :title="__('Podium')" class="tshow__podium">
                        {{-- 1-2-3 in the DOM, 2-1-3 on screen. See .podium.

                             Keyed on the result's own place, not on its
                             position in the list: third can be settled while
                             first and second are not, and by index that lone
                             bronze finisher would be dressed in gold. --}}
                        <ol class="podium">
                            @foreach ($podium as $winner)
                                <li class="podium__place podium__place--{{ $winner->place }}">
                                    <span class="podium__seat">{{ strtoupper(substr($winner->player_name, 0, 1)) }}</span>
                                    <span class="podium__name">{{ $winner->player_name }}</span>
                                    <span class="podium__step">{{ $winner->place }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </x-card>
                @endif

                @if ($isPast)
                    <x-card :title="__('Final Standings')" flush class="tshow__standings">
                        <x-table>
                            <x-slot name="head">
                                <th scope="col">{{ __('Pos') }}</th>
                                <th scope="col">{{ __('Player') }}</th>
                                <th scope="col" class="table__num">{{ __('Points') }}</th>
                            </x-slot>

                            @forelse ($orderedResults as $result)
                                <tr>
                                    <td><x-rank :place="$result->place" /></td>

                                    <td>
                                        <div class="entry__title">{{ $result->player_name }}</div>

                                        @if (filled($result->player_nickname))
                                            <div class="entry__meta"><span>{{ $result->player_nickname }}</span></div>
                                        @endif
                                    </td>

                                    <td class="table__num">{{ number_format($result->points) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-empty-state :title="__('No results recorded yet.')" />
                                    </td>
                                </tr>
                            @endforelse
                        </x-table>
                    </x-card>
                @endif

                <x-card :title="__('Registered Players')" flush class="tshow__players">
                    <x-slot name="actions">
                        <x-badge>{{ $registrantsCount }}</x-badge>
                    </x-slot>

                    @forelse ($tournament->registrants->sortBy('player_name') as $registrant)
                        @php $result = $resultsByUser[$registrant->user_id] ?? null; @endphp

                        <div class="entry">
                            <span class="podium__seat">{{ strtoupper(substr($registrant->player_name, 0, 1)) }}</span>

                            <div class="entry__body">
                                <div class="entry__title">{{ $registrant->player_name }}</div>

                                @if (filled($registrant->player_nickname))
                                    <div class="entry__meta"><span>{{ $registrant->player_nickname }}</span></div>
                                @endif
                            </div>

                            <div class="entry__actions">
                                @if ($result)
                                    {{-- Already out. Their finish replaces the
                                         control, so the row says what happened
                                         rather than offering to do it again. --}}
                                    <x-badge>{{ \Illuminate\Support\Number::ordinal($result->place) }}
                                        &middot; {{ number_format($result->points) }} {{ __('pts') }}</x-badge>
                                @elseif (auth()->user()->is_admin && ! $tournament->registration_open)
                                    {{-- Only once the field is settled: a late
                                         entry would change how many places there
                                         are, and the ones already awarded would
                                         be wrong. The controller refuses it in
                                         that state too.

                                         The confirmation names the place and the
                                         points because neither is chosen here --
                                         they fall out of how many players are
                                         still in, and an administrator should
                                         see what they are about to award before
                                         they award it. --}}
                                    <form action="{{ route('poker.tournaments.eliminate', $tournament) }}"
                                          method="POST"
                                          data-confirm="{{ __('Eliminate :name? They finish in :place place and are awarded :points points. This records a tournament result.', [
                                              'name' => $registrant->player_name,
                                              'place' => \Illuminate\Support\Number::ordinal($nextPlace),
                                              'points' => number_format($nextPlacePoints),
                                          ]) }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $registrant->user_id }}">

                                        <x-btn variant="ghost" size="sm" type="submit">{{ __('Eliminate') }}</x-btn>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-empty-state :title="__('No players registered yet.')" />
                    @endforelse
                </x-card>
            </div>

            <div class="l-stack">
                {{-- is_admin alone. The card used to require a non-empty list
                     as well, so once everyone was entered the register control
                     vanished with no explanation -- which reads as a bug rather
                     than as a state, the same reasoning that keeps "Awaiting
                     approval" on the event card instead of hiding its button.
                     The empty case now says what happened. --}}
                @if (auth()->user()->is_admin)
                    {{-- A searchable list, not a <select>. The select could not
                         be searched past the browser's type-to-jump, which
                         matches the start of the label only -- so an
                         administrator who knew a nickname or an email address
                         had no way to use it.

                         $availableUsers is already the right set: the
                         controller excludes anyone registered for this
                         tournament and anyone unapproved, because register()
                         refuses both and offering them would be offering a
                         button that fails. --}}
                    <x-card :title="__('Admin: Register')" class="tshow__register">
                        @php
                            // One lowercase haystack per row, built once here
                            // rather than in the filter: the search has to cover
                            // name, nickname and email, and doing that in the
                            // expression would repeat four fields in two places.
                            $haystack = fn ($u) => mb_strtolower(trim(
                                $u->first_name.' '.$u->last_name.' '.$u->nickname.' '.$u->email
                            ));
                        @endphp

                        <div class="l-stack l-stack--tight"
                             x-data="{ q: '', terms: {{ Illuminate\Support\Js::from($availableUsers->map($haystack)->values()) }} }">
                            <label class="u-visually-hidden" for="player-search">
                                {{ __('Search players') }}
                            </label>

                            <input id="player-search" type="search" class="field__control"
                                   x-model="q" placeholder="{{ __('Name, nickname or email') }}">

                            @if ($availableUsers->isEmpty())
                                <x-empty-state :title="__('Everyone is registered')">
                                    {{ __('Every approved player is already entered in this tournament.') }}
                                </x-empty-state>
                            @else
                                <ul class="picker">
                                    @foreach ($availableUsers as $user)
                                        {{-- Rendered server-side and hidden by the
                                             filter, rather than built from the
                                             array in x-data. If the script never
                                             runs, an administrator still sees every
                                             player and can still register one; an
                                             x-for list would be empty. --}}
                                        <li class="picker__item"
                                            data-search="{{ $haystack($user) }}"
                                            x-show="$el.dataset.search.includes(q.trim().toLowerCase())">
                                            <form action="{{ route('tournaments.register', $tournament) }}"
                                                  method="POST"
                                                  {{-- The one confirmation here that is not
                                                       destructive, so its Confirm button is not
                                                       red. The browser's dialog had no colour to
                                                       get wrong; a styled one does. --}}
                                                  data-confirm-tone="primary"
                                                  data-confirm="{{ __('Register :name for :tournament?', [
                                                      'name' => $user->first_name.' '.$user->last_name,
                                                      'tournament' => $tournament->name,
                                                  ]) }}">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $user->id }}">

                                                <button type="submit" class="picker__btn">
                                                    <span class="picker__name">
                                                        {{ $user->first_name }} {{ $user->last_name }}{{ filled($user->nickname) ? ' ('.$user->nickname.')' : '' }}
                                                    </span>

                                                    <span class="picker__meta">{{ $user->email }}</span>
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>

                                {{-- The terms array exists for this line alone.
                                     The rows filter themselves from their own
                                     data-search; knowing whether ANY of them
                                     matched needs the set. --}}
                                <p class="picker__empty"
                                   x-show="terms.filter(t => t.includes(q.trim().toLowerCase())).length === 0"
                                   x-cloak>
                                    {{ __('No players match that search.') }}
                                </p>
                            @endif
                        </div>
                    </x-card>
                @endif

                @if (! $isPast && $pointsStructure->isNotEmpty())
                    <x-card :title="__('Points at Stake')" class="tshow__points">
                        <dl class="rows">
                            @foreach ($pointsStructure as $structure)
                                <div class="row">
                                    <dt class="row__label">
                                        {{ $structure->place }}{{ match ($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                                        {{ __('Place') }}
                                    </dt>
                                    <dd class="row__value">{{ number_format($structure->points) }} {{ __('Pts') }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <p class="field__hint">{{ __('Points are based on league rules.') }}</p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
