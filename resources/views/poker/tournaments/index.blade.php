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
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($tournaments as $tournament)
                    <tr>
                        <td class="tournaments-index__name">{{ $tournament->name }}</td>

                        <td class="tournaments-index__venue">{{ $tournament->venue->name ?? __('TBD') }}</td>

                        <td class="tournaments-index__season">{{ $tournament->season->name }}</td>

                        <td class="tournaments-index__start">{{ $tournament->start_time?->format('M d, Y · h:i A') ?? '—' }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                            {{-- tournaments.show is open to anyone signed in --
                                 it is where a player registers. Everything
                                 below it changes a record. --}}
                            <x-action icon="view" :label="__('View')" :href="route('tournaments.show', $tournament)" />

                                @if (auth()->user()->is_admin)
                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.tournaments.edit', $tournament)" />

                                <form action="{{ route('poker.tournaments.destroy', $tournament) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $tournament->name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-action icon="delete" :label="__('Delete')" danger />
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
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
