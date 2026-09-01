<x-public-layout>
    <x-p-hero suit="diamond" :eyebrow="__('League Rules & Logic')"
              :title="__('Points Structure')"
              :highlight="__('Structure')">
        {{ __('Our proprietary scoring algorithms reward consistency, deep runs, and tournament dominance. Points form the backbone of our seasonal rankings.') }}
    </x-p-hero>

    {{-- Signed in only: a standings preview is a member view, and its link goes
         somewhere a guest has no reason to land. --}}
    @auth
        @if ($topPerformers->isNotEmpty())
            <section class="p-panel">
                <div class="p-panel__glow" aria-hidden="true"></div>

                <h2 class="p-panel__eyebrow">{{ __('Current Season Leaders') }}</h2>

                <ol class="p-leaders">
                    @foreach ($topPerformers as $performer)
                        <li class="p-leader p-leader--{{ min($loop->iteration, 3) }}">
                            <span class="p-leader__seat">{{ $loop->iteration }}</span>

                            <div class="p-leader__body">
                                <span class="p-leader__name">{{ $performer->first_name }} {{ $performer->last_name }}</span>

                                @if (filled($performer->nickname))
                                    <span class="p-leader__nickname">{{ $performer->nickname }}</span>
                                @endif
                            </div>

                            <span class="p-leader__points">
                                {{ number_format($performer->tournament_results_sum_points ?? 0) }}
                            </span>
                        </li>
                    @endforeach
                </ol>

                <a class="p-panel__link" href="{{ route('seasons.show', $currentSeason) }}">
                    {{ __('Full Season Standings') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </section>
        @endif
    @endauth

    <x-table>
        <x-slot name="head">
            <th scope="col">{{ __('Rank') }}</th>
            <th scope="col">{{ __('Placement') }}</th>
            <th scope="col" class="table__num">{{ __('Awarded Points') }}</th>
        </x-slot>

        @forelse ($pointsStructure as $structure)
            <tr>
                <td><x-rank :place="$structure->place" /></td>

                <td>
                    <div class="p-tier">
                        {{ $structure->place }}{{ match ($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                        {{ __('Place') }}
                    </div>

                    @if ($structure->place === 1)
                        <x-badge variant="accent">{{ __('Tournament Champion') }}</x-badge>
                    @elseif ($structure->place <= 3)
                        <x-badge variant="primary">{{ __('Podium Level') }}</x-badge>
                    @endif
                </td>

                <td class="table__num">
                    <div class="p-points">
                        <span class="p-points__value">{{ number_format($structure->points) }}</span>
                        <span class="p-points__unit">{{ __('Pts') }}</span>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3">
                    <x-empty-state :title="__('Standard league structure is being finalized.')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <footer class="p-page-foot">
        <p class="p-note">
            {{ __('Points are verified by the league steward. In the event of ties, prize pools are split but points are awarded to the higher finishing position in the official bracket.') }}
        </p>
    </footer>
</x-public-layout>
