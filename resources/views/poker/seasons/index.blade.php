<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Poker Seasons')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.seasons.create')">{{ __('Create Season') }}</x-btn>
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
                    <th scope="col">{{ __('Current') }}</th>
                    <th scope="col">{{ __('Start Date') }}</th>
                    <th scope="col">{{ __('End Date') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($seasons as $season)
                    <tr>
                        <td>{{ $season->name }}</td>

                        <td>
                            @if ($season->is_current)
                                <x-badge variant="open">{{ __('Current') }}</x-badge>
                            @endif
                        </td>

                        <td>{{ $season->start_date?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $season->end_date?->format('M d, Y') ?? '—' }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <x-action icon="stats" :label="__('View Stats')" :href="route('seasons.show', $season)" />

                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.seasons.edit', $season)" />

                                {{-- data-confirm, never an inline onsubmit. Blade escapes
                                     the name for HTML, but the browser HTML-decodes an
                                     attribute before parsing its contents as JS, so a name
                                     containing an apostrophe would break out of the string
                                     literal. See resources/js/confirm.ts. --}}
                                <form action="{{ route('poker.seasons.destroy', $season) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $season->name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-action icon="delete" :label="__('Delete')" danger />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        {{-- colspan was 4 on a five-column table. --}}
                        <td colspan="5">
                            <x-empty-state :title="__('No seasons found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($seasons->hasPages())
                <div class="card__pager">{{ $seasons->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
