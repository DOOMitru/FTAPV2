<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Tournament Registrants')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('poker.registrants.create')">{{ __('Add Registrant') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        {{-- The filter leads the page: what you are looking at, before what
             is in it. --}}
        <x-tournament-filter :tournaments="$tournaments" :selected="$selected" />

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            <x-table cards class="registrants-index__table">
                <x-slot name="head">
                    <th scope="col">{{ __('Tournament') }}</th>
                    <th scope="col">{{ __('Player') }}</th>
                    <th scope="col">{{ __('Registered At') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($registrants as $registrant)
                    <tr>
                        <td class="registrants-index__tournament">{{ $registrant->tournament->name }}</td>

                        <td class="registrants-index__player">
                            {{-- An inner row, not display:flex on the <td>: a
                                 flexed cell stops being a table-cell and drops
                                 out of the column alignment on desktop. --}}
                            <div class="admin-list__player">
                                <x-monogram :user="$registrant->user" :name="$registrant->player_name" size="sm" decorative />

                                <div>
                                    <div class="entry__title">
                                        <x-player-link :user="$registrant->user">{{ $registrant->player_name }}</x-player-link>
                                    </div>

                                    @if ($registrant->player_nickname)
                                        <div class="entry__meta"><span>{{ $registrant->player_nickname }}</span></div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td class="registrants-index__date">{{ $registrant->registered_at ? $registrant->registered_at->format('M d, Y') : '—' }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <x-action icon="edit" :label="__('Edit')" :href="route('poker.registrants.edit', $registrant)" />

                                {{-- Only while the field can still change. Once
                                     finishes are recorded, a place describes the
                                     size of the field, and taking a player out of
                                     it makes every one of those places wrong --
                                     so the controller refuses, and offering the
                                     button anyway is offering a click that cannot
                                     work. --}}
                                {{-- Per registrant, and per tournament state.
                                     What cannot be removed is a player with a
                                     finish of their own -- their place is a
                                     position in the field -- and a field that
                                     has been published, which is settled for
                                     good. One elimination used to close this
                                     for everybody in that tournament, including
                                     the people still playing. --}}
                                @unless ($registrant->hasFinished() || $registrant->tournament->isPublished())
                                    <form action="{{ route('poker.registrants.destroy', $registrant) }}" method="POST"
                                          data-confirm="{{ __('Remove :name from :tournament? This cannot be undone.', [
                                              'name' => emph($registrant->player_name),
                                              'tournament' => emph($registrant->tournament->name),
                                          ]) }}">
                                        @csrf
                                        @method('DELETE')

                                        <x-action icon="delete" :label="__('Delete')" danger />
                                    </form>
                                @endunless
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
