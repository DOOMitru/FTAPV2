@props(['tournaments', 'selected' => null])

{{--
    The tournament a list is showing, and a way to change it.

    Rendered as a search field over a server-rendered list rather than a <select>
    with an x-for: a league accumulates tournaments, and picking one out of forty
    by scrolling a native select is worse than typing three letters of its name.
    The same reasoning, and the same .picker markup, as the player pickers on the
    venue-points form and the tournament page.

    Every option is a plain link carrying ?tournament=. That means the filter
    survives a page reload, can be bookmarked, works with the back button, and
    needs no JavaScript to apply -- Alpine only narrows what is already on the
    page.
--}}
@if ($tournaments->isNotEmpty())
    <div class="tournament-filter" x-data="{ q: '' }">
        <span class="tournament-filter__label">{{ __('Showing') }}</span>

        <x-dropdown align="left" class="tournament-filter__menu">
            <x-slot name="trigger">
                <button type="button" class="tournament-filter__trigger">
                    <span>{{ $selected?->name ?? __('All tournaments') }}</span>

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                {{-- x-model, not a form field: this box filters what is drawn and
                     is never submitted. --}}
                <input type="search" class="tournament-filter__search" x-model="q"
                       placeholder="{{ __('Search tournaments') }}"
                       aria-label="{{ __('Search tournaments') }}">

                <ul class="picker">
                    @foreach ($tournaments as $tournament)
                        {{-- Rendered server-side and hidden by the filter rather
                             than built from an array: a list that exists in the
                             document can be read by a screen reader and found by
                             the browser's own search. --}}
                        <li class="picker__item"
                            data-search="{{ Str::lower($tournament->name.' '.$tournament->start_time?->format('M d, Y')) }}"
                            x-show="$el.dataset.search.includes(q.trim().toLowerCase())">
                            <a class="picker__btn{{ $selected?->is($tournament) ? ' picker__btn--current' : '' }}"
                               href="{{ request()->fullUrlWithQuery(['tournament' => $tournament->id, 'page' => null]) }}">
                                <span class="picker__name">{{ $tournament->name }}</span>

                                <span class="picker__meta">
                                    {{ $tournament->start_time?->format('M d, Y') ?? __('No date') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-slot>
        </x-dropdown>
    </div>
@endif
