<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="$tournament->name">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="ghost" :href="route('poker.tournaments.index')">{{ __('Back') }}</x-btn>
                    <x-btn variant="primary" :href="route('poker.tournaments.edit', $tournament)">{{ __('Edit') }}</x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        {{-- The same card the events and home pages draw, so the three cannot
             drift. This was a bespoke <x-card flush> laid out with .l-sidebar,
             which handed the map two thirds of the width and left the details
             in the remaining third with no padding at all: the registration
             time sat hard against the card's edge and, on a past event, the
             podium was clipped off the bottom.

             No Details button -- this IS the details page. --}}
        <x-p-event :tournament="$tournament" :details="false">
            @if ($tournament->description)
                <p class="u-muted">{{ $tournament->description }}</p>
            @endif

            <x-slot name="actions">
                {{-- Unregister lives only here, so the shared card does not
                     carry it. Both conditions are the controller's: it refuses
                     an unregister once registration has closed. --}}
                @if ($isUserRegistered && $tournament->registration_open)
                    <form action="{{ route('tournaments.unregister', $tournament) }}" method="POST"
                          data-confirm="{{ __('Are you sure you want to unregister from this tournament?') }}">
                        @csrf
                        @method('DELETE')
                        <x-btn variant="ghost" type="submit">{{ __('Unregister') }}</x-btn>
                    </form>
                @endif
            </x-slot>
        </x-p-event>

        <div class="l-grid">
            <x-stat :label="__('Registrants')" :value="$registrantsCount" />

            @if ($isPast)
                <x-stat :label="__('Final Results')" :value="$resultsCount" />
                <x-stat :label="__('Avg Points')"
                        :value="$resultsCount ? number_format($totalPoints / $resultsCount) : '0'" />
                <x-stat :label="__('Points Pot')" :value="number_format($totalPoints)" />
            @endif
        </div>

        <div class="l-sidebar">
            <div class="l-stack">
                @if ($isPast && $podium->isNotEmpty())
                    <x-card :title="__('Podium')">
                        {{-- 1-2-3 in the DOM, 2-1-3 on screen. See .podium. --}}
                        <ol class="podium">
                            @foreach ($podium as $index => $winner)
                                <li class="podium__place podium__place--{{ $index + 1 }}">
                                    <span class="podium__seat">{{ strtoupper(substr($winner->player_name, 0, 1)) }}</span>
                                    <span class="podium__name">{{ $winner->player_name }}</span>
                                    <span class="podium__step">{{ $index + 1 }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </x-card>
                @endif

                @if ($isPast)
                    <x-card :title="__('Final Standings')" flush>
                        <x-table>
                            <x-slot name="head">
                                <th scope="col">{{ __('Pos') }}</th>
                                <th scope="col">{{ __('Player') }}</th>
                                <th scope="col" class="table__num">{{ __('Points') }}</th>
                            </x-slot>

                            @forelse ($orderedResults as $result)
                                <tr>
                                    <td><x-rank :place="$result->place" /></td>

                                    <td>
                                        <div class="entry__title">{{ $result->player_name }}</div>

                                        @if (filled($result->player_nickname))
                                            <div class="entry__meta"><span>{{ $result->player_nickname }}</span></div>
                                        @endif
                                    </td>

                                    <td class="table__num">{{ number_format($result->points) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-empty-state :title="__('No results recorded yet.')" />
                                    </td>
                                </tr>
                            @endforelse
                        </x-table>
                    </x-card>
                @endif

                <x-card :title="__('Registered Players')" flush>
                    <x-slot name="actions">
                        <x-badge>{{ $registrantsCount }}</x-badge>
                    </x-slot>

                    @forelse ($tournament->registrants->sortBy('player_name') as $registrant)
                        <div class="entry">
                            <span class="podium__seat">{{ strtoupper(substr($registrant->player_name, 0, 1)) }}</span>

                            <div class="entry__body">
                                <div class="entry__title">{{ $registrant->player_name }}</div>

                                @if (filled($registrant->player_nickname))
                                    <div class="entry__meta"><span>{{ $registrant->player_nickname }}</span></div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-empty-state :title="__('No players registered yet.')" />
                    @endforelse
                </x-card>
            </div>

            <div class="l-stack">
                @if (auth()->user()->is_admin && $availableUsers->isNotEmpty())
                    <x-card :title="__('Admin: Register')">
                        <form action="{{ route('tournaments.register', $tournament) }}" method="POST" class="l-stack">
                            @csrf

                            <x-field name="user_id" :label="__('Select Player')">
                                <select class="field__control" name="user_id" id="user_id" required>
                                    <option value="">{{ __('-- Choose User --') }}</option>

                                    @foreach ($availableUsers as $user)
                                        <option value="{{ $user->id }}">
                                            {{ $user->first_name }} {{ $user->last_name }}{{ filled($user->nickname) ? ' ('.$user->nickname.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-field>

                            <x-btn variant="primary" class="btn--block">{{ __('Register Player') }}</x-btn>
                        </form>
                    </x-card>
                @endif

                @if (! $isPast && $pointsStructure->isNotEmpty())
                    <x-card :title="__('Points at Stake')">
                        <dl class="rows">
                            @foreach ($pointsStructure as $structure)
                                <div class="row">
                                    <dt class="row__label">
                                        {{ $structure->place }}{{ match ($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                                        {{ __('Place') }}
                                    </dt>
                                    <dd class="row__value">{{ number_format($structure->points) }} {{ __('Pts') }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <p class="field__hint">{{ __('Points are based on league rules.') }}</p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
