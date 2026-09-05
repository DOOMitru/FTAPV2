<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Add Venue Points')">
            {{-- In the header, not beside Save. The form returns to itself
                 after every entry, so leaving it is a different kind of act
                 from the entry being typed. --}}
            <x-slot name="actions">
                <x-btn variant="ghost" :href="route('poker.venue-points.index')">{{ __('Back') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        {{-- The form comes back to itself after every save, so this is the only
             place the confirmation can land. --}}
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            @php
                // A return trip. The sitting came back in the query string, so
                // the venue and the date are already answered and the only
                // thing left to do is name the next player -- which is where
                // the caret should be, rather than on a venue select that is
                // going to be tabbed straight past a dozen times a night.
                $returning = filled($venueId) && filled($eventDate);

                // One lowercase haystack per row, built once here rather than in
                // the filter: the search has to cover name, nickname and email,
                // and doing that in the expression would repeat four fields in
                // two places. The same helper the tournament picker uses.
                $haystack = fn ($u) => mb_strtolower(trim(
                    $u->first_name.' '.$u->last_name.' '.$u->nickname.' '.$u->email
                ));
            @endphp

            {{-- The state lives on the form rather than on the picker, so that
                 choosing a player can reach the amount input by ref. $refs only
                 sees what is inside the same x-data. --}}
            <form method="POST" action="{{ route('poker.venue-points.store') }}" class="l-stack"
                  x-data="{ q: '', chosen: null }">
                @csrf

                {{-- Venue and date first, because they are the sitting: one
                     venue on one night, and a dozen players who were there.
                     Both carry over to the next entry, so they are asked once
                     and then left alone. --}}
                <x-field name="venue_id" :label="__('Venue')">
                    <select class="field__control" name="venue_id" id="venue_id" required
                            @if (! $returning) autofocus @endif>
                        <option value="">{{ __('Select Venue') }}</option>

                        @foreach ($venues as $venue)
                            <option value="{{ $venue->id }}"
                                    @selected(old('venue_id', $venueId) == $venue->id)>{{ $venue->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="event_date" :label="__('Event Date')" type="date"
                         :value="old('event_date', $eventDate)" required />

                {{-- The player, from a searchable list rather than a select of
                     every account. A select is fine for a handful of options and
                     useless at two hundred: it cannot be searched past the
                     browser's type-to-jump, which matches the start of the label
                     only, so an administrator who knows a nickname or an email
                     address had no way to use it. --}}
                <div class="l-stack l-stack--tight">
                    <span class="field__label">{{ __('Player') }}</span>

                    {{-- What the form actually posts. user_name is stored beside
                         user_id so a record still says who it was for after an
                         account is gone; it holds "First Last", exactly what the
                         select's autofill used to write into a visible field. --}}
                    <input type="hidden" name="user_id" x-bind:value="chosen ? chosen.id : ''">
                    <input type="hidden" name="user_name" x-bind:value="chosen ? chosen.name : ''">

                    {{-- Once chosen, the list gives way to the name. Twelve
                         entries for one night means twelve trips through this
                         control, and a two-hundred-row list left open under a
                         made choice is noise on every one of them. --}}
                    <div class="field-row" x-show="chosen" x-cloak>
                        <p class="field__static field-row__grow" x-text="chosen ? chosen.name : ''"></p>

                        <div class="field-row__actions">
                            <div class="l-cluster l-cluster--end">
                                <x-btn variant="ghost" x-on:click="chosen = null; q = ''">{{ __('Change') }}</x-btn>
                            </div>
                        </div>
                    </div>

                    <div class="l-stack l-stack--tight" x-show="! chosen">
                        <label class="u-visually-hidden" for="player-search">{{ __('Search players') }}</label>

                        <input id="player-search" type="search" class="field__control"
                               x-model="q" placeholder="{{ __('Name, nickname or email') }}"
                               @if ($returning) autofocus @endif>

                        @if ($users->isEmpty())
                            <x-empty-state :title="__('No players yet')">
                                {{ __('Venue points are recorded against an account, and there are none.') }}
                            </x-empty-state>
                        @else
                            <ul class="picker">
                                @foreach ($users as $user)
                                    {{-- Rendered server-side and hidden by the
                                         filter rather than built from an array
                                         in x-data: a list that exists in the
                                         document can be read by a screen reader
                                         and found by the browser's own search. --}}
                                    <li class="picker__item"
                                        data-search="{{ $haystack($user) }}"
                                        x-show="$el.dataset.search.includes(q.trim().toLowerCase())">
                                        {{-- type="button": this sits inside the
                                             form that Save submits, and a bare
                                             button would post it. --}}
                                        <button type="button" class="picker__btn"
                                                x-on:click="chosen = { id: @js($user->id), name: @js(trim($user->first_name.' '.$user->last_name)) }; $nextTick(() => $refs.amount.focus())">
                                            <span class="picker__name">
                                                {{ $user->first_name }} {{ $user->last_name }}{{ filled($user->nickname) ? ' ('.$user->nickname.')' : '' }}
                                            </span>

                                            <span class="picker__meta">{{ $user->email }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- user_id is posted from a hidden input, so its failure
                         has no control of its own to sit under. --}}
                    <x-input-error :messages="$errors->get('user_id')" />
                    <x-input-error :messages="$errors->get('user_name')" />
                </div>

                <x-field name="amount" :label="__('Amount')" type="number"
                         :value="old('amount')"
                         :hint="__('Whole dollars only, rounded to the nearest dollar.')"
                         :hint-inline="true"
                         required x-ref="amount" />

                <div class="l-cluster l-cluster--end">
                    <x-btn variant="primary">{{ __('Save') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
