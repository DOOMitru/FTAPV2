<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Poker Seasons')">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="primary" :href="route('poker.seasons.create')">{{ __('Create Season') }}</x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            {{-- The mobile layout is CSS only: same cells, reflowed by
                 _seasons-index.css below 48rem. Start and end are two columns
                 holding one fact -- a season's span -- so on a phone they read
                 as the range they are. --}}
            <x-table cards class="seasons-index__table">
                <x-slot name="head">
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Current') }}</th>
                    <th scope="col">{{ __('Start Date') }}</th>
                    <th scope="col">{{ __('End Date') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($seasons as $season)
                    <tr>
                        <td class="seasons-index__name">{{ $season->name }}</td>

                        <td class="seasons-index__current">
                            @if ($season->is_current)
                                <x-badge variant="open">{{ __('Current') }}</x-badge>
                            @endif
                        </td>

                        <td class="seasons-index__start">{{ $season->start_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="seasons-index__end">{{ $season->end_date?->format('M d, Y') ?? '—' }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                {{-- Reading a season is why a player is on this
                                     page; seasons.show is open to anyone signed
                                     in. Everything below it changes a record. --}}
                                <x-action icon="stats" :label="__('View Stats')" :href="route('seasons.show', $season)" />

                                @if (auth()->user()->is_admin)
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
                                @endif
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
