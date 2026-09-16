<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="Auth::user()->first_name.' '.Auth::user()->last_name"
                       :title="__('Dashboard')" />
    </x-slot>

    <div class="l-container l-stack">
        {{-- One panel, not seven tiles.
             
             Seven figures about one season, and three of them -- firsts,
             seconds, thirds -- are one idea. Split across a four-wide grid they
             were three cards each holding a small integer, usually 0, with an
             orphan row underneath.

             Rank leads because it is the question ("how am I doing"), and it
             shows the division it comes from: it is points per event entered,
             so a bare #4 is a number the reader cannot check or argue with. --}}
        {{-- The season IS the title. It was a badge beside the button, which
             put the name of the thing this panel is about in the corner
             furthest from it, under a heading that said "Current Season" --
             true of every season, and so of no help in telling them apart. --}}
        <x-season-panel :season="$season" :current-season="$currentSeason" />

        {{-- One column. The side column held Active Season alone, and that
             has been folded into the season panel above -- a sidebar grid
             with nothing in its sidebar leaves these two cards at two thirds
             width with a column of nothing beside them. --}}
        {{-- No actions. The header carried a count of every scheduled event and
             a button to the public schedule; the note under the list already
             says how many there are and links to the same page, so the header
             was a second copy of both in the corner furthest from the list they
             describe.

             $upcomingCount survives for that note. --}}
        <x-card :title="__('Upcoming Tournaments')" flush>

            @forelse ($upcomingTournaments as $tournament)
                @php
                    $isReg = $tournament->registrants->contains('user_id', Auth::id());
                    // The date you play. This chip used to read the
                    // registration deadline, an hour earlier, so it
                    // could show the wrong day either side of midnight.
                    $when = $tournament->start_time;
                @endphp

                <div class="entry">
                    <span class="date-chip">
                        <span class="date-chip__month">{{ $when->format('M') }}</span>
                        <span class="date-chip__day">{{ $when->format('d') }}</span>
                    </span>

                    <div class="entry__body">
                        <div class="entry__title">{{ $tournament->name }}</div>

                        <div class="entry__meta">
                            {{-- "Closes 7:00 PM" until the deadline went.
                                 It read the deadline then; with that gone
                                 it would have gone on saying "Closes"
                                 over the time play STARTS, which is a
                                 label that actively misleads. --}}
                            <span>{{ $when->format('g:i A') }}</span>
                            <span>{{ $tournament->venue->name ?? __('TBD') }}</span>
                        </div>
                    </div>

                    <div class="entry__actions">
                        @if ($isReg)
                            <x-badge variant="open">{{ __('Registered') }}</x-badge>

                            {{-- The badge says where you stand; the
                                 button is how you change it. Saying the
                                 first without offering the second left
                                 the details page as the only way out of
                                 a tournament.

                                 Ghost, not danger: leaving is not what
                                 this row is asking you to do.

                                 The condition is the controller's --
                                 it refuses a withdrawal once a finish is
                                 recorded -- so past that point the badge
                                 stands alone, which is the truth of it. --}}
                            @if (! $tournament->hasRecordedResults())
                                <form action="{{ route('tournaments.unregister', $tournament) }}" method="POST"
                                      data-confirm="{{ __('Unregister from :tournament? You can enter again any time before results are recorded.', [
                                          'tournament' => emph($tournament->name),
                                      ]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-btn variant="ghost" size="sm" type="submit">{{ __('Unregister') }}</x-btn>
                                </form>
                            @endif
                        @else
                            {{-- Approval is the controller's gate too, and
                                 it refuses an unapproved account. Offering
                                 the button anyway is offering a click that
                                 cannot work; saying why is what the event
                                 card does in the same situation. --}}
                            @if (auth()->user()->isApproved())
                                <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
                                    @csrf
                                    <x-btn variant="primary" size="sm">{{ __('Register') }}</x-btn>
                                </form>
                            @else
                                <x-badge>{{ __('Awaiting approval') }}</x-badge>
                            @endif
                        @endif

                        <x-btn variant="ghost" size="sm" :href="route('tournaments.show', $tournament)">
                            {{ __('View') }}
                        </x-btn>
                    </div>
                </div>
            @empty
                <x-empty-state :title="__('No upcoming tournaments scheduled.')" />
            @endforelse

            {{-- Only when the cap actually hides something. "Showing the next 5
                 of 3" is worse than saying nothing, and a standing note on a
                 list that is complete is noise -- the same rule the Recent
                 Results note follows below. --}}
            @if ($upcomingCount > $upcomingTournaments->count())
                {{-- Two parts in their own elements, so they can take a line
                     each when there is not room for both. The spacing between
                     them is the flex gap now rather than a trailing space
                     inside a translatable string. --}}
                <p class="card__note card__note--split">
                    <span>{{ trans_choice(
                        '{1}The next one of :count scheduled.|[2,*]The next :shown of :count scheduled.',
                        $upcomingTournaments->count(),
                        ['shown' => $upcomingTournaments->count(), 'count' => $upcomingCount]
                    ) }}</span>

                    <a class="link" href="{{ route('events') }}">{{ __('See the full schedule') }}</a>
                </p>
            @endif
        </x-card>

        <x-card :title="__('Recent Results')" flush>
            <x-table>
                <x-slot name="head">
                    <th scope="col">{{ __('Tournament') }}</th>
                    <th scope="col">{{ __('Place') }}</th>
                    <th scope="col" class="table__num">{{ __('Points') }}</th>
                </x-slot>

                @forelse ($userResults->take(5) as $result)
                    {{-- The whole row is the link, not the name in it.
                         One anchor stretched over the row by
                         .table__link, so the place and the points are
                         part of the target too -- a row of three short
                         cells offers a poor one otherwise, especially on
                         a phone.

                         One anchor also because three would be three:
                         the name, the date and the tournament are the
                         same destination, and a list of five rows would
                         read as fifteen links. The same reasoning as
                         .entry--link on the venue page, which cannot be
                         reused here because a <tr> may not be wrapped in
                         an <a>. --}}
                    <tr class="table__row--link">
                        <td>
                            <div class="entry__title">
                                <a class="table__link"
                                   href="{{ route('tournaments.show', $result->tournament) }}">{{ $result->tournament->name }}</a>
                            </div>
                            <div class="entry__meta">
                                <span>{{ $result->tournament->start_time->format('M d, Y') }}</span>
                            </div>
                        </td>

                        <td><x-rank :place="$result->place" /></td>

                        <td class="table__num">{{ number_format($result->points) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state :title="__('No result data recorded yet.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($userResults->count() > 5)
                <p class="card__note">{{ __('Showing latest 5 career results') }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
