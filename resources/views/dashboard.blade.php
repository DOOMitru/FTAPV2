<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="Auth::user()->first_name.' '.Auth::user()->last_name"
                       :title="__('Dashboard')" />
    </x-slot>

    <div class="l-container l-stack">
        {{-- Labels are asserted by test_dashboard_preserves_career_figures on the
             collapsed text, e.g. "Career Points 645". <x-stat> renders label then
             value, which keeps that adjacency. --}}
        {{-- --tight so the four tiles are two abreast on a phone instead of
             four stacked cards. --}}
        <div class="l-grid l-grid--tight">
            <x-stat :label="__('Career Points')" :value="number_format($totalPoints)" />
            <x-stat :label="__('Events Played')" :value="$tournamentsPlayed" />
            <x-stat :label="__('Podium Finishes')" :value="$podiums" />
            <x-stat :label="__('Tournament Wins')" :value="$wins" />
        </div>

        <div class="l-sidebar">
            <div class="l-stack">
                <x-card :title="__('Upcoming Tournaments')" flush>
                    <x-slot name="actions">
                        <x-badge>{{ $upcomingTournaments->count() }} {{ __('Events') }}</x-badge>
                    </x-slot>

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
                                        <span>{{ \Illuminate\Support\Carbon::parse($result->tournament->start_time)->format('M d, Y') }}</span>
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

                    @if ($tournamentsPlayed > 5)
                        <p class="card__note">{{ __('Showing latest 5 career results') }}</p>
                    @endif
                </x-card>
            </div>

            <div class="l-stack">
                <x-card :title="__('Active Season')">
                    <p class="entry__title">{{ $currentSeason->name ?? __('None') }}</p>

                    <dl class="rows">
                        <div class="row">
                            <dt class="row__label">{{ __('Season Rank') }}</dt>
                            <dd class="row__value">{{ $seasonRank ? '#'.$seasonRank : '—' }}</dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">{{ __('Season Points') }}</dt>
                            <dd class="row__value">{{ number_format($seasonPoints) }}</dd>
                        </div>
                    </dl>

                    @if ($currentSeason)
                        <x-slot name="actions">
                            <x-btn variant="ghost" size="sm" :href="route('seasons.show', $currentSeason)">
                                {{ __('Full Season Stats') }}
                            </x-btn>
                        </x-slot>
                    @endif
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
