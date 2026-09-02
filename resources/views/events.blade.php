<x-public-layout>
    <x-p-hero suit="diamond" :eyebrow="__('League Schedule')"
              :title="__('Upcoming Events')"
              :highlight="__('Events')">
        {{ __('Every league night, in one place. Each card carries its venue, its start time, and the deadline to register.') }}
    </x-p-hero>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert variant="danger">{{ session('error') }}</x-alert>
    @endif

    <div class="l-stack">
        @forelse ($upcomingTournaments as $tournament)
            <x-p-event :tournament="$tournament" />
        @empty
            <x-empty-state :title="__('No Scheduled Events')">
                {{ __('No events are scheduled yet. Dates for the next season go up here first.') }}
            </x-empty-state>
        @endforelse
    </div>

    {{ $upcomingTournaments->links() }}

    @if ($pastTournaments->isNotEmpty())
        <section>
            <div class="p-part">
                <h2 class="p-part__label">{{ __('Tournament Archives') }}</h2>
                <span class="p-part__line" aria-hidden="true"></span>
            </div>

            <div class="p-archive">
                @foreach ($pastTournaments as $tournament)
                    <a class="p-archive__card p-raised p-lift" href="{{ route('tournaments.show', $tournament) }}">
                        <div class="l-cluster l-cluster--between">
                            <span class="u-eyebrow">{{ $tournament->start_time->format('M Y') }}</span>
                            <x-badge>{{ __('Completed') }}</x-badge>
                        </div>

                        <h3 class="p-archive__title">{{ $tournament->name }}</h3>

                        <span class="p-leader__nickname">{{ $tournament->venue->name ?? __('Location TBD') }}</span>

                        {{-- The settled places only -- see
                             PokerTournament::podium(). This was
                             sortBy('place')->take(3), which is the current best
                             three: on a tournament still being played out they
                             are the last few knocked out, not the podium. --}}
                        @php $podium = $tournament->podium(); @endphp

                        @if ($podium->isNotEmpty())
                            <ol class="p-podium">
                                @foreach ($podium as $result)
                                    <li class="p-podium__row">
                                        <x-rank :place="$result->place" />
                                        <span class="p-podium__name">{{ $result->player_name }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <div class="p-archive__foot">
                            <span class="p-archive__more">{{ __('Full results') }}</span>

                            <svg class="p-archive__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-public-layout>
