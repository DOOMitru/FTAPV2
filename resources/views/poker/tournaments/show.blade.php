<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="$tournament->name">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="ghost" :href="route('poker.tournaments.index')">{{ __('Back') }}</x-btn>

                    {{-- Publishing is the end of the tournament: it tells the
                         players who scored and locks the record. Offered only
                         when it can act, so it is never a click that fails --
                         the controller refuses an incomplete field too. --}}
                    @if (! $tournament->isPublished() && $tournament->isComplete())
                        <form action="{{ route('poker.tournaments.publish', $tournament) }}" method="POST"
                              data-confirm-tone="primary"
                              data-confirm="{{ __('Publish results for :tournament? Every player who scored points will be notified, and the tournament will be locked.', [
                                  'tournament' => $tournament->name,
                              ]) }}">
                            @csrf
                            <x-btn variant="primary" type="submit">{{ __('Publish results') }}</x-btn>
                        </form>
                    @endif

                    {{-- Reopening retracts what publishing said, so the
                         confirmation says so rather than only asking. --}}
                    @if ($tournament->isPublished())
                        <form action="{{ route('poker.tournaments.unpublish', $tournament) }}" method="POST"
                              data-confirm="{{ __('Unpublish :tournament? The notifications sent to players will be withdrawn.', [
                                  'tournament' => $tournament->name,
                              ]) }}">
                            @csrf
                            @method('DELETE')

                            <x-btn variant="ghost" type="submit">{{ __('Unpublish') }}</x-btn>
                        </form>
                    @endif

                    {{-- Edit steps down while Publish is on offer. Two primary
                         buttons side by side is two calls to action and
                         therefore none; when a tournament is finished, the
                         thing to do with it is publish it. --}}
                    <x-btn :variant="! $tournament->isPublished() && $tournament->isComplete() ? 'ghost' : 'primary'"
                           :href="route('poker.tournaments.edit', $tournament)">{{ __('Edit') }}</x-btn>
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
             drift.

             No Details button -- this IS the details page. No map either: the
             venue name is in the card and the address is one click away, and
             this page is reached by people already inside the dashboard.

             The podium used to sit at the top of the left column. Final
             Standings, directly below, already names the same three players in
             the same order and keeps going, so the podium restated its first
             three rows at the cost of a card. --}}
        <x-p-event :tournament="$tournament" :details="false" :map="false">
            @if ($tournament->description)
                <p class="u-muted">{{ $tournament->description }}</p>
            @endif

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
                {{-- One panel, not two. Final Standings and Registered Players
                     listed the same people in the same order and differed only
                     in what they put beside a name: a medal and a points total
                     on one, a monogram and the controls on the other. A player
                     appeared twice, and an administrator read down one list to
                     find a name and across to the other to act on it.

                     The leading glyph carries the difference the two lists used
                     to carry between them. A player still in is their initials;
                     once they have a place, they are that place, medalled for
                     the top three. One circle per row either way, and the row
                     says at a glance whether this player is still playing.

                     Rows with a result are ordered best first, and the players
                     still in sit above them -- they are competing for the places
                     above the ones already awarded. --}}
                <x-card :title="$standingsTitle" flush class="tshow__players">
                    <x-slot name="actions">
                        <x-badge>{{ $registrantsCount }}</x-badge>
                    </x-slot>

                    @forelse ($standings as $row)
                        @php $result = $row['result']; @endphp

                        <div class="entry">
                            @if ($result)
                                <x-rank :place="$result->place" />
                            @else
                                <x-monogram :user="$row['user']" :name="$row['name']" decorative />
                            @endif

                            <div class="entry__body">
                                <div class="entry__title">{{ $row['name'] }}</div>

                                @if (filled($row['nickname']))
                                    <div class="entry__meta"><span>{{ $row['nickname'] }}</span></div>
                                @endif
                            </div>

                            <div class="entry__actions">
                                @if ($result)
                                    {{-- Points only. The place is the glyph at
                                         the head of the row, and printing it
                                         again here is the one duplication the
                                         merge was supposed to remove. --}}
                                    <x-badge>{{ number_format($result->points) }} {{ __('pts') }}</x-badge>
                                @elseif (auth()->user()->is_admin)
                                    {{-- No timing gate. This once required
                                         registration closed, on the reasoning
                                         that a late entry would change how many
                                         places there are -- but the shift hook
                                         handles exactly that, moving recorded
                                         finishes down when someone joins after
                                         the fact.

                                         The confirmation names the place and the
                                         points because neither is chosen here --
                                         they fall out of how many players are
                                         still in, and an administrator should
                                         see what they are about to award before
                                         they award it. --}}
                                    <form action="{{ route('poker.tournaments.eliminate', $tournament) }}"
                                          method="POST"
                                          data-confirm="{{ __('Eliminate :name? They finish in :place place and are awarded :points points. This records a tournament result.', [
                                              'name' => $row['name'],
                                              'place' => \Illuminate\Support\Number::ordinal($nextPlace),
                                              'points' => number_format($nextPlacePoints),
                                          ]) }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $row['registrant']?->user_id }}">

                                        <x-btn variant="ghost" size="sm" type="submit">{{ __('Eliminate') }}</x-btn>
                                    </form>
                                @endif

                                {{-- Removing a mistaken entry, from the page an
                                     administrator is on when they notice it.
                                     This was only reachable from the registrants
                                     index, which is a list of every entry in
                                     every tournament.

                                     Tied to $resultsCount and nothing else,
                                     because a place is a position in a field
                                     -- tenth of ten -- and taking a player out
                                     afterwards makes every recorded finish
                                     describe a tournament that never happened.
                                     The controller refuses it for the same
                                     reason.

                                     Once ANY result exists the control is gone
                                     from every row, not just from the players
                                     who have finished. That is the rule: the
                                     field is settled as a whole.

                                     The registrant check is belt and braces:
                                     the route needs a registrant, and a row
                                     built from a result alone has none. It
                                     cannot fire today -- a registrant-less row
                                     implies a result, and a result closes this
                                     control for every row -- so nothing tests
                                     it. It guards the row's shape rather than
                                     the tournament's state. --}}
                                @if (auth()->user()->is_admin && $resultsCount === 0 && $row['registrant'])
                                    <form action="{{ route('poker.registrants.destroy', $row['registrant']) }}"
                                          method="POST"
                                          data-confirm="{{ __('Remove :name from :tournament? They will no longer be entered, and can register again any time before results are recorded.', [
                                              'name' => $row['name'],
                                              'tournament' => $tournament->name,
                                          ]) }}">
                                        @csrf
                                        @method('DELETE')

                                        <x-action icon="delete" :label="__('Remove from tournament')" danger />
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-empty-state :title="__('No players registered yet.')" />
                    @endforelse

                    {{-- Why the remove control is not there any more. An admin
                         who used it last week and finds it gone this week reads
                         a missing control as a bug, not as a state -- the same
                         reasoning that keeps "Awaiting approval" on the event
                         card rather than just hiding Register. Admins only: a
                         player was never offered it and has nothing to explain. --}}
                    @if (auth()->user()->is_admin && $resultsCount > 0 && $registrantsCount > 0)
                        <p class="card__note">{{ __('Results recorded · entries locked') }}</p>
                    @endif
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
                                    <dd class="row__value">
                                        {{ number_format($structure->points) }}
                                        <span class="row__unit">{{ __('Pts') }}</span>
                                    </dd>
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
