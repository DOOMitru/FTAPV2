<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Tournament Results')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.results.create')">{{ __('Add Result') }}</x-btn>
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
                    <th scope="col">{{ __('Tournament') }}</th>
                    <th scope="col">{{ __('Place') }}</th>
                    <th scope="col">{{ __('Player') }}</th>
                    <th scope="col" class="table__num">{{ __('Points') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($results as $result)
                    <tr>
                        <td>{{ $result->tournament->name }}</td>

                        <td><x-rank :place="$result->place" /></td>

                        <td>
                            <div class="entry__title">{{ $result->player_name }}</div>

                            @if ($result->player_nickname)
                                <div class="entry__meta"><span>{{ $result->player_nickname }}</span></div>
                            @endif
                        </td>

                        <td class="table__num">{{ number_format($result->points) }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <a class="link" href="{{ route('poker.results.edit', $result) }}">{{ __('Edit') }}</a>

                                <form action="{{ route('poker.results.destroy', $result) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $result->player_name]) }}">
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
                            <x-empty-state :title="__('No results found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($results->hasPages())
                <div class="card__pager">{{ $results->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
