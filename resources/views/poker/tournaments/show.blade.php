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
                                  'tournament' => emph($tournament->name),
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
                                  'tournament' => emph($tournament->name),
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

                    {{-- Deleting a tournament is offered where it is read, not
                         from a row in a listing: an administrator removing one
                         has the field, the finishes and whether it is published
                         in front of them.

                         The confirmation names what goes with it. A row in a
                         list could say "this cannot be undone" and mean the
                         tournament; here it can say what else the cascade
                         takes, which is the thing somebody would want to know
                         before clicking. --}}
                    <form action="{{ route('poker.tournaments.destroy', $tournament) }}" method="POST"
                          data-confirm="{{ __('Delete :name? Its registrations and every recorded finish go with it. This cannot be undone.', [
                              'name' => emph($tournament->name),
                          ]) }}">
                        @csrf
                        @method('DELETE')

                        <x-btn variant="danger" type="submit">{{ __('Delete') }}</x-btn>
                    </form>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    {{-- The Alpine scope spans the page because the button that opens the
         dialog lives inside the standings card and the dialog itself does not.
         q and players are only read inside the dialog.

         matches, with no search term, leaves out anybody who cannot be picked
         -- already in this tournament, or still waiting for approval. The list
         is capped at ten, and a league's most frequent entrants, who sort to
         the top, are exactly the people most likely to be entered already, so
         the default view was spending its ten slots on rows that did nothing.

         With a term it returns everyone matching, pickable or not: that is when
         an administrator is hunting one named person, and "they are already in"
         or "they are waiting for approval" is the answer they came for.

         The reasoning lives out here rather than in the expression. A comment
         inside the attribute has to avoid the double quote that delimits it,
         and one that did not ended the attribute early and took the whole
         component down with it -- see AlpineAttributeGuardTest. --}}
    <div class="l-container l-stack"
         @if (auth()->user()->is_admin && ! $tournament->isPublished())
         x-data="{
             q: '',
             players: {{ Illuminate\Support\Js::from($registerCandidates) }},
             get matches() {
                 const t = this.q.trim().toLowerCase();
                 return t === ''
                     ? this.players.filter(p => ! p.registered && p.approved)
                     : this.players.filter(p => p.search.includes(t));
             },
             get shown() { return this.matches.slice(0, 10); },
         }"
         @endif
    >
        {{-- Suppressed while the register dialog is reopening: it sits in the
             top layer with a backdrop over the page, so these would be
             announcing the same thing to an admin who cannot see them. The
             dialog carries its own copy. --}}
        @if (session('status') && ! session('register_open'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if (session('error') && ! session('register_open'))
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

        {{-- One panel, so no .l-sidebar. The page was two columns -- the
             standings beside the admin register panel and the points table --
             and both of those have gone: the points table to the public rules
             page, and registering to a dialog. A sidebar grid with nothing in
             its sidebar leaves the standings at two thirds width with a column
             of nothing beside it. --}}
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

                {{-- On the list rather than in the page header, which already
                     carries Back, Publish, Unpublish and Edit. This adds a
                     player to this list, so it belongs on it.

                     Hidden once published for the same reason register()
                     refuses then: a published tournament's field is settled. --}}
                @if (auth()->user()->is_admin && ! $tournament->isPublished())
                    {{-- The plus is doing real work. Without it this was a small
                         ghost button beside a count badge in a card header, and
                         it read as a second label rather than as the control
                         that adds a player -- it was missed entirely. --}}
                    {{-- The words are clipped on a phone, not removed: the
                         button still reads as "Register players" to a screen
                         reader, where display:none would take its only
                         accessible name away and leave a plus sign. Same
                         technique as the pager's Previous and Next. --}}
                    <x-btn variant="ghost" size="sm" type="button" class="btn--icon-lead tshow__register-btn"
                           x-on:click="$refs.registerDialog.showModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>

                        <span class="tshow__register-label">{{ __('Register players') }}</span>
                    </x-btn>
                @endif
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
                        <div class="entry__title">
                            <x-player-link :user="$row['user']">{{ $row['name'] }}</x-player-link>
                        </div>

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
                                      'name' => emph($row['name']),
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

                             Per row, not per tournament. What cannot be
                             removed is somebody who has a finish of their
                             own: their place is a position in the field, and
                             deleting the position makes every other place
                             describe a tournament that never happened. A
                             player still in has no such position, so they can
                             go, and the shrink hook moves the recorded
                             finishes up to match.

                             ! $result is load-bearing, not decoration. This
                             is its own @if rather than a branch of the chain
                             above, so without it an eliminated player gets
                             their points badge AND a remove button that the
                             controller then refuses.

                             Gone once published, which is the point at which
                             the field is settled for good. The registrant
                             check stays: the route needs a registrant, and a
                             row built from a result alone has none. --}}
                        @if (auth()->user()->is_admin && ! $result && ! $tournament->isPublished() && $row['registrant'])
                            <form action="{{ route('poker.registrants.destroy', $row['registrant']) }}"
                                  method="POST"
                                  data-confirm="{{ __('Remove :name from :tournament? Any finishes already recorded move up a place and are repriced. An administrator can enter them again until the results are published.', [
                                      'name' => emph($row['name']),
                                      'tournament' => emph($tournament->name),
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

            {{-- Publishing is now the only thing that locks the field, so
                 that is what the note says. It read "Results recorded ·
                 entries locked", which was true when the first elimination
                 closed removal for the whole field and is false now:
                 everyone still playing can be taken out, and an eliminated
                 player shows their finish where the controls would be,
                 which explains itself. --}}
            @if (auth()->user()->is_admin && $tournament->isPublished() && $registrantsCount > 0)
                <p class="card__note">{{ __('Results published · field locked') }}</p>
            @endif
        </x-card>

        {{-- Asked the moment the field is settled.
             
             Rendered only when it could be answered yes, and opened only on the
             request that flashed the offer -- so it appears once, right after
             the last elimination, and never again on a reload.

             A native <dialog> for the same reasons as the two beside it: focus
             moves in and is trapped, Escape closes, the page behind goes inert.
             No data-confirm on the form, because this dialog IS the
             confirmation -- the header's Publish button keeps its own, since
             nothing asked first when you press that one. --}}
        @if (auth()->user()->is_admin && ! $tournament->isPublished() && $tournament->isComplete())
            <dialog class="confirm publish-offer"
                    @if (session('offer_publish')) x-init="$el.showModal()" @endif
                    aria-labelledby="publish-offer-title">
                <p class="confirm__message" id="publish-offer-title">
                    {{ __('That is the whole field for :tournament. Publish the results now?', [
                        'tournament' => $tournament->name,
                    ]) }}
                </p>

                <p class="publish-offer__note">
                    {{ __('Every player who scored points is notified, and the tournament is locked.') }}
                </p>

                <div class="confirm__actions">
                    {{-- method="dialog": closes and posts nothing. The offer is
                         flashed, so declining here does not have to record
                         anything -- the next request simply will not ask. --}}
                    <form method="dialog">
                        <x-btn variant="ghost" type="submit">{{ __('Not yet') }}</x-btn>
                    </form>

                    <form action="{{ route('poker.tournaments.publish', $tournament) }}" method="POST">
                        @csrf
                        <x-btn variant="primary" type="submit">{{ __('Publish results') }}</x-btn>
                    </form>
                </div>
            </dialog>
        @endif

        @if (auth()->user()->is_admin && ! $tournament->isPublished())
            {{-- A native <dialog> opened with showModal(), for the reasons
                 spelled out on x-confirm-dialog: focus moves in and is trapped,
                 Escape closes, the page behind goes inert, and it renders in the
                 top layer. The Alpine modal beside it reimplements all four.

                 It stays open across registrations. Each row posts a normal form
                 and the page reloads, so "stays open" is a server fact rather
                 than a client one: register() flashes register_open when an
                 administrator registers somebody else, and x-init reopens on the
                 way back in. That keeps the list, the counts and the already-
                 registered marks correct after every addition, which an
                 in-place update would have to maintain by hand.

                 No data-confirm on these rows. The point of the dialog is to add
                 several players in a row, and a confirmation on each defeats it
                 -- registration is reversible from the same page while no result
                 exists, which is what makes that safe.

                 The list is built by x-for rather than server-rendered and
                 hidden. The panel this replaced did the opposite, so an
                 administrator without JavaScript could still register someone;
                 that argument does not survive the move, because showModal() is
                 JavaScript and without it the dialog never opens at all. --}}
            <dialog class="register" x-ref="registerDialog"
                    @if (session('register_open')) x-init="$el.showModal()" @endif
                    aria-labelledby="register-dialog-title">
                <div class="register__head">
                    {{-- Not "Register players for <tournament>": a league's
                         tournament names run to "The Main Event Season 4", which
                         wrapped the heading onto two lines and pushed it into
                         the Done button. The dialog sits on that tournament's
                         own page and the confirmation after each registration
                         names it, so the heading does not have to. --}}
                    <h2 class="register__title" id="register-dialog-title">
                        {{ __('Register players') }}
                    </h2>

                    {{-- method="dialog": the button closes the dialog and needs
                         no handler of its own, and Escape lands in the same
                         place. --}}
                    <form method="dialog">
                        <x-btn variant="ghost" size="sm" type="submit">{{ __('Done') }}</x-btn>
                    </form>
                </div>

                {{-- What just happened, said where the administrator is
                     looking. The page-level alert is behind the backdrop while
                     the dialog is open, so on its own it announced each
                     registration to nobody -- and registering a dozen players
                     in a row is exactly when you want to see that the last one
                     landed.

                     The name is set apart rather than left inside the sentence:
                     after eight of these the sentence is wallpaper and the name
                     is the only part being read. --}}
                @if (session('registered_name'))
                    <p class="register__flash register__flash--done">
                        <span class="register__flash-name">{{ session('registered_name') }}</span>
                        {{ __('has been registered.') }}
                    </p>
                @endif

                @if (session('error'))
                    {{-- emph_html, not {{ }}: this one is a raw session value
                         rather than a slot, so it is escaped and split in one
                         step. --}}
                    <p class="register__flash register__flash--error">{{ emph_html(session('error')) }}</p>
                @endif

                <label class="u-visually-hidden" for="register-search">{{ __('Search players') }}</label>

                <input id="register-search" type="search" class="field__control register__search"
                       x-model="q" placeholder="{{ __('Name, nickname or email') }}">

                <ul class="picker register__list">
                    <template x-for="p in shown" :key="p.id">
                        <li class="picker__item">
                            {{-- Already in this tournament: named, so the
                                 administrator can see why they are not on
                                 offer, and inert, because register() refuses
                                 them. Absence would leave "already entered" and
                                 "not approved" looking identical.

                                 Only reached when something has been typed --
                                 see the filter in x-data. --}}
                            <template x-if="p.registered">
                                <div class="picker__btn picker__btn--inert" aria-disabled="true">
                                    {{-- <x-monogram> renders server-side and
                                         these rows do not exist until Alpine
                                         builds them, so the row carries the
                                         component's classes and its letters
                                         come from the payload -- computed by
                                         the same initials() the component
                                         itself calls. --}}
                                    <span class="monogram monogram--sm picker__face"
                                          aria-hidden="true" x-text="p.initials"></span>

                                    <span class="picker__name" x-text="p.label"></span>
                                    <span class="picker__meta">{{ __('Already registered for this tournament') }}</span>
                                </div>
                            </template>

                            {{-- Approved, but not yet in this tournament. The
                                 refusal reads differently from the one above
                                 and so does the remedy: this administrator can
                                 approve the account, and telling them which
                                 gate is shut is the whole point of listing a
                                 player they cannot pick. --}}
                            <template x-if="! p.registered && ! p.approved">
                                <div class="picker__btn picker__btn--inert" aria-disabled="true">
                                    {{-- <x-monogram> renders server-side and
                                         these rows do not exist until Alpine
                                         builds them, so the row carries the
                                         component's classes and its letters
                                         come from the payload -- computed by
                                         the same initials() the component
                                         itself calls. --}}
                                    <span class="monogram monogram--sm picker__face"
                                          aria-hidden="true" x-text="p.initials"></span>

                                    <span class="picker__name" x-text="p.label"></span>
                                    <span class="picker__meta">{{ __('Waiting for approval — approve the account first') }}</span>
                                </div>
                            </template>

                            <template x-if="! p.registered && p.approved">
                                <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="user_id" :value="p.id">

                                    <button type="submit" class="picker__btn">
                                        <span class="monogram monogram--sm picker__face"
                                              aria-hidden="true" x-text="p.initials"></span>

                                        <span class="picker__name" x-text="p.label"></span>
                                        <span class="picker__meta" x-text="p.email"></span>
                                        <span class="picker__count"
                                              x-text="p.played === 1
                                                  ? '1 {{ __('tournament') }}'
                                                  : p.played + ' {{ __('tournaments') }}'"></span>
                                    </button>
                                </form>
                            </template>
                        </li>
                    </template>
                </ul>

                <p class="picker__empty" x-show="matches.length === 0" x-cloak>
                    {{ __('No players match that search.') }}
                </p>

                {{-- Only when the cap actually hides somebody. A standing note
                     that ten are shown, on a list of four, is noise. --}}
                <p class="register__note" x-show="matches.length > 10" x-cloak>
                    <span x-text="matches.length"></span>
                    {{ __('players match. Showing the ten who have entered the most tournaments — search to narrow it.') }}
                </p>
            </dialog>
        @endif
    </div>
</x-app-layout>
