<x-app-layout>
    <x-slot name="header">
        {{-- The name is the title. Whose figures these are is the whole
             question this page answers, so it is not an eyebrow above a
             heading that would read "Player" on every one of them. --}}
        {{-- The full name, not display_name: that prefers a nickname and falls
             back to the first name alone, which is the right thing to say out
             loud beside an avatar and the wrong thing to head a profile with.
             The nickname leads instead, where a name has one. --}}
        <x-page-header :eyebrow="filled($player->nickname) ? $player->nickname : __('Player')"
                       :title="trim($player->first_name.' '.$player->last_name)">
            @if ($isSelf)
                <x-slot name="actions">
                    <x-btn variant="ghost" size="sm" :href="route('dashboard')">
                        {{ __('Your dashboard') }}
                    </x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        {{-- The same panel the dashboard draws, about somebody else. It takes
             self so the empty-rank copy can speak about a third party, and it
             leaves out venue points entirely when the controller has withheld
             them. --}}
        <x-season-panel :season="$season" :current-season="$currentSeason" :self="$isSelf" />

        <x-card :title="__('Recent Results')" flush>
            <x-table>
                <x-slot name="head">
                    <th scope="col">{{ __('Tournament') }}</th>
                    <th scope="col">{{ __('Place') }}</th>
                    <th scope="col" class="table__num">{{ __('Points') }}</th>
                </x-slot>

                @forelse ($results->take(5) as $result)
                    {{-- The whole row is the link, as on the dashboard: three
                         short cells offer a poor target otherwise, and three
                         separate anchors to one tournament would read as
                         fifteen links down a list of five. --}}
                    <tr class="table__row--link">
                        <td>
                            <div class="entry__title">
                                <a class="table__link"
                                   href="{{ route('tournaments.show', $result->tournament) }}">{{ $result->tournament->name }}</a>
                            </div>
                            <div class="entry__meta">
                                <span>{{ $result->tournament->start_time->format('M d, Y') }}</span>
                            </div>
                        </td>

                        <td><x-rank :place="$result->place" /></td>

                        <td class="table__num">{{ number_format($result->points) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            {{-- About them, not to them: this page is read by
                                 somebody else more often than not. --}}
                            <x-empty-state :title="__('No results recorded yet.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($results->count() > 5)
                <p class="card__note">{{ __('Showing latest 5 career results') }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
