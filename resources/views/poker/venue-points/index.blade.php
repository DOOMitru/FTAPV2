<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Venue Points')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.venue-points.create')">{{ __('Add Points') }}</x-btn>
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
                    <th scope="col">{{ __('Date') }}</th>
                    <th scope="col">{{ __('Player') }}</th>
                    <th scope="col">{{ __('Venue') }}</th>
                    <th scope="col" class="table__num">{{ __('Amount') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($venue_points as $point)
                    @php
                        // Worked out once. The cells and the confirmation have
                        // to be describing the same row, and formatting the
                        // date twice is how they would stop.
                        $date = $point->event_date
                            ? \Illuminate\Support\Carbon::parse($point->event_date)->format('M d, Y')
                            : null;

                        $venueName = $point->venue->name ?? null;

                        // Names the record, not the person holding it. This
                        // said "Delete Ada Lovelace?", which is a truthful
                        // description of a different and much worse button.
                        //
                        // One sentence, not a short and a long one: event_date
                        // is NOT NULL and venue_id cascades on delete, so a row
                        // without either cannot exist and a branch for it would
                        // be untestable. The fallbacks are for a broken foreign
                        // key, and read as a sentence because this is one.
                        $confirm = __('Delete :amount venue points for :name at :venue on :date? This cannot be undone.', [
                            'amount' => number_format($point->amount),
                            'name' => $point->user_name,
                            'venue' => $venueName ?? __('an unknown venue'),
                            'date' => $date ?? __('an unknown date'),
                        ]);
                    @endphp

                    <tr>
                        <td>{{ $date ?? '—' }}</td>

                        <td>{{ $point->user_name }}</td>

                        <td>{{ $venueName ?? __('TBD') }}</td>

                        <td class="table__num">{{ number_format($point->amount) }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.venue-points.edit', $point)" />

                                <form action="{{ route('poker.venue-points.destroy', $point) }}" method="POST"
                                      data-confirm="{{ $confirm }}">
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
                            <x-empty-state :title="__('No venue points found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($venue_points->hasPages())
                <div class="card__pager">{{ $venue_points->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
