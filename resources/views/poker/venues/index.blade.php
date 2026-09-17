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

                </x-slot>

                @forelse ($venues as $venue)
                    {{-- Linked for an administrator only. The venue detail page
                         is admin-only, so a row a player could follow is a row
                         that leads to a 403 -- the same reason this table gave
                         a player no View Stats control when it had one.

                         The row carries the link and nothing else: editing and
                         deleting a venue are offered on the venue's own page,
                         where the takings and the leaderboard are in front of
                         whoever is about to change it. --}}
                    @php $linked = auth()->user()->is_admin; @endphp

                    <tr class="venue-row{{ $linked ? ' table__row--link' : '' }}">
                        <td class="venue-row__name">
                            @if ($linked)
                                <a class="table__link"
                                   href="{{ route('poker.venues.show', $venue) }}">{{ $venue->name }}</a>
                            @else
                                {{ $venue->name }}
                            @endif
                        </td>

                        {{-- No whitespace inside: the mobile rule hides this
                             cell with :empty, and a stray newline would make it
                             a cell with content as far as CSS is concerned. --}}
                        <td class="venue-row__desc">{{ $venue->description }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">
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
