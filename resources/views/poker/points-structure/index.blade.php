<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Points Structure')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.points-structure.create')">{{ __('Add Place') }}</x-btn>
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
                    <th scope="col">{{ __('Place') }}</th>
                    <th scope="col" class="table__num">{{ __('Points') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($structures as $structure)
                    <tr>
                        <td><x-rank :place="$structure->place" /></td>

                        <td class="table__num">{{ number_format($structure->points) }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <a class="link" href="{{ route('poker.points-structure.edit', $structure) }}">{{ __('Edit') }}</a>

                                <form action="{{ route('poker.points-structure.destroy', $structure) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $structure->place.__(' place')]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="link link--danger">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state :title="__('No points structure defined.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($structures->hasPages())
                <div class="card__pager">{{ $structures->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
