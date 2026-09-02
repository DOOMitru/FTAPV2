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

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
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
                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.points-structure.edit', $structure)" />

                                {{-- The deepest place only. The ladder shrinks
                                     from the bottom, so offering Delete on a
                                     middle row would be offering a control the
                                     controller refuses. --}}
                                @if ($structure->place === $lastPlace)
                                    <form action="{{ route('poker.points-structure.destroy', $structure) }}" method="POST"
                                          data-confirm="{{ __('Remove :place place, worth :points points?', [
                                              'place' => \Illuminate\Support\Number::ordinal($structure->place),
                                              'points' => number_format($structure->points),
                                          ]) }}">
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
