<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Poker Tournaments')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.tournaments.create')">{{ __('Schedule Tournament') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            <x-table>
                <x-slot name="head">
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Venue') }}</th>
                    <th scope="col">{{ __('Season') }}</th>
                    <th scope="col">{{ __('Start Time') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($tournaments as $tournament)
                    <tr>
                        <td>{{ $tournament->name }}</td>

                        <td>{{ $tournament->venue->name ?? __('TBD') }}</td>

                        <td>{{ $tournament->season->name }}</td>

                        <td>{{ $tournament->start_time?->format('M d, Y · h:i A') ?? '—' }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                            <x-action icon="view" :label="__('View')" :href="route('tournaments.show', $tournament)" />

                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.tournaments.edit', $tournament)" />

                                <form action="{{ route('poker.tournaments.destroy', $tournament) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $tournament->name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-action icon="delete" :label="__('Delete')" danger />
                                </form>
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
