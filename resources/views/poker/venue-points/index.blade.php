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
                    <tr>
                        <td>{{ $point->event_date ? \Illuminate\Support\Carbon::parse($point->event_date)->format('M d, Y') : '—' }}</td>

                        <td>{{ $point->user_name }}</td>

                        <td>{{ $point->venue->name ?? __('TBD') }}</td>

                        <td class="table__num">{{ number_format($point->amount) }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <a class="link" href="{{ route('poker.venue-points.edit', $point) }}">{{ __('Edit') }}</a>

                                <form action="{{ route('poker.venue-points.destroy', $point) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $point->user_name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="link link--danger">{{ __('Delete') }}</button>
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
