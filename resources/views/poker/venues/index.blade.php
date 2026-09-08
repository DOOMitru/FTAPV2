<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Venues')">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="primary" :href="route('poker.venues.create')">{{ __('Add Venue') }}</x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            <x-table class="venues-table">
                <x-slot name="head">
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Description') }}</th>

                    {{-- The whole column, not just its contents. A player gets
                         no control here at all -- not even View Stats, because
                         the venue detail page is admin-only and a link to it is
                         a link to a 403 -- so an empty Actions column would be
                         a heading over nothing. --}}
                    @if (auth()->user()->is_admin)
                        <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                    @endif
                </x-slot>

                @forelse ($venues as $venue)
                    <tr class="venue-row">
                        <td class="venue-row__name">{{ $venue->name }}</td>

                        {{-- No whitespace inside: the mobile rule hides this
                             cell with :empty, and a stray newline would make it
                             a cell with content as far as CSS is concerned. --}}
                        <td class="venue-row__desc">{{ $venue->description }}</td>

                        @if (auth()->user()->is_admin)
                        <td class="table__actions venue-row__actions">
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
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->is_admin ? 3 : 2 }}">
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
