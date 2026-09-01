<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="Auth::user()->first_name.' '.Auth::user()->last_name"
                       :title="__('Dashboard')" />
    </x-slot>

    <div class="l-container l-stack">
        {{-- Labels are asserted by test_dashboard_preserves_career_figures on the
             collapsed text, e.g. "Career Points 645". <x-stat> renders label then
             value, which keeps that adjacency. --}}
        <div class="l-grid">
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
                            $when = \Illuminate\Support\Carbon::parse($tournament->scheduled_at);
                        @endphp

                        <div class="entry">
                            <span class="date-chip">
                                <span class="date-chip__month">{{ $when->format('M') }}</span>
                                <span class="date-chip__day">{{ $when->format('d') }}</span>
                            </span>

                            <div class="entry__body">
                                <div class="entry__title">{{ $tournament->name }}</div>

                                <div class="entry__meta">
                                    <span>{{ __('Closes') }} {{ $when->format('h:i A') }}</span>
                                    <span>{{ $tournament->venue->name ?? __('TBD') }}</span>
                                </div>
                            </div>

                            <div class="entry__actions">
                                @if ($isReg)
                                    <x-badge variant="primary">{{ __('Registered') }}</x-badge>
                                @elseif ($tournament->registration_open)
                                    <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
                                        @csrf
                                        <x-btn variant="primary" size="sm">{{ __('Sign Up') }}</x-btn>
                                    </form>
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
                            <tr>
                                <td>
                                    <div class="entry__title">{{ $result->tournament->name }}</div>
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

                <x-card :title="__('Points Structure')">
                    <x-slot name="actions">
                        <a class="link" href="{{ route('rules.points-structure') }}">{{ __('Full Rules') }}</a>
                    </x-slot>

                    @php $pts = \App\Models\PointsStructure::orderBy('place')->take(5)->get(); @endphp

                    <dl class="rows">
                        @foreach ($pts as $p)
                            <div class="row">
                                <dt class="row__label">
                                    {{ $p->place }}{{ match ($p->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                                    {{ __('Place') }}
                                </dt>
                                <dd class="row__value">{{ number_format($p->points) }} {{ __('pts') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
