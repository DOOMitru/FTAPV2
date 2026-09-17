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
                    <th scope="col" class="table__num">{{ __('Start Date') }}</th>
                    <th scope="col" class="table__num">{{ __('End Date') }}</th>
                </x-slot>

                @forelse ($seasons as $season)
                    {{-- The whole row is the link, and the only control on it.
                         Editing and deleting a season are offered on the
                         season's own page: an administrator changing one is
                         reading it first, and a row of three icons beside
                         every name is a column of decisions on a page whose
                         job is to list.

                         One anchor stretched over the row by .table__link, so
                         the dates and the Current badge are part of the target
                         too -- and so a list of twenty seasons reads as twenty
                         links rather than eighty. --}}
                    <tr class="table__row--link">
                        <td class="seasons-index__name">
                            <a class="table__link"
                               href="{{ route('seasons.show', $season) }}">{{ $season->name }}</a>
                        </td>

                        <td class="seasons-index__current">
                            @if ($season->is_current)
                                <x-badge variant="open">{{ __('Current') }}</x-badge>
                            @endif
                        </td>

                        {{-- table__num: right-aligned and tabular, so the two
                             date columns line up digit under digit. The phone
                             layout is unaffected -- its cells are inline, and
                             text-align does not reach an inline box. --}}
                        <td class="table__num seasons-index__start">{{ $season->start_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="table__num seasons-index__end">{{ $season->end_date?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
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
