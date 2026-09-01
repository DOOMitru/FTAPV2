<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Venues')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.venues.create')">{{ __('Add Venue') }}</x-btn>
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
                    <th scope="col">{{ __('Description') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($venues as $venue)
                    <tr>
                        <td>{{ $venue->name }}</td>

                        <td>{{ $venue->description }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                            <x-action icon="stats" :label="__('View Stats')" :href="route('poker.venues.show', $venue)" />

                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.venues.edit', $venue)" />

                                <form action="{{ route('poker.venues.destroy', $venue) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $venue->name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-action icon="delete" :label="__('Delete')" danger />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state :title="__('No venues found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($venues->hasPages())
                <div class="card__pager">{{ $venues->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
