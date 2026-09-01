<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Tournament Registrants')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.registrants.create')">{{ __('Add Registrant') }}</x-btn>
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
                    <th scope="col">{{ __('Player') }}</th>
                    <th scope="col">{{ __('Registered At') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($registrants as $registrant)
                    <tr>
                        <td>{{ $registrant->tournament->name }}</td>

                        <td>
                            <div class="entry__title">{{ $registrant->player_name }}</div>

                            @if ($registrant->player_nickname)
                                <div class="entry__meta"><span>{{ $registrant->player_nickname }}</span></div>
                            @endif
                        </td>

                        <td>{{ $registrant->registered_at ? \Illuminate\Support\Carbon::parse($registrant->registered_at)->format('M d, Y') : '—' }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <a class="link" href="{{ route('poker.registrants.edit', $registrant) }}">{{ __('Edit') }}</a>

                                <form action="{{ route('poker.registrants.destroy', $registrant) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $registrant->player_name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="link link--danger">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state :title="__('No registrants found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($registrants->hasPages())
                <div class="card__pager">{{ $registrants->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
