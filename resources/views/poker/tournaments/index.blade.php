<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Poker Tournaments')">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="primary" :href="route('poker.tournaments.create')">{{ __('Schedule Tournament') }}</x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            <x-table cards class="tournaments-index__table">
                <x-slot name="head">
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Venue') }}</th>
                    <th scope="col">{{ __('Season') }}</th>
                    <th scope="col">{{ __('Start Time') }}</th>
                </x-slot>

                @forelse ($tournaments as $tournament)
                    {{-- The whole row is the link, and the only control on it.
                         tournaments.show is open to anyone signed in -- it is
                         where a player registers -- and editing or deleting a
                         tournament is offered there, behind the admin gate the
                         page already applies.

                         One anchor stretched over the row by .table__link, so
                         the venue, the season and the time are part of the
                         target too. --}}
                    <tr class="table__row--link">
                        <td class="tournaments-index__name">
                            <a class="table__link"
                               href="{{ route('tournaments.show', $tournament) }}">{{ $tournament->name }}</a>
                        </td>

                        <td class="tournaments-index__venue">{{ $tournament->venue->name ?? __('TBD') }}</td>

                        <td class="tournaments-index__season">{{ $tournament->season->name }}</td>

                        <td class="tournaments-index__start">{{ $tournament->start_time?->format('M d, Y · h:i A') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state :title="__('No tournaments found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($tournaments->hasPages())
                <div class="card__pager">{{ $tournaments->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
