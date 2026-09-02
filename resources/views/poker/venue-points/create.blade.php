<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Add Venue Points')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.venue-points.store') }}" class="l-stack">
                @csrf

                <x-field name="venue_id" :label="__('Venue')">
                    <select class="field__control" name="venue_id" id="venue_id" required autofocus>
                        <option value="">{{ __('Select Venue') }}</option>

                        @foreach ($venues as $venue)
                            <option value="{{ $venue->id }}"
                                    @selected(old('venue_id', null) == $venue->id)>{{ $venue->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="user_id" :label="__('Player')">
                    <select class="field__control" name="user_id" id="user_id" required data-autofill='{"first":"#user_name"}'>
                        <option value="">{{ __('Select User') }}</option>

                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" data-first-name="{{ $user->first_name }}"
                                    data-last-name="{{ $user->last_name }}"
                                    data-nickname="{{ $user->nickname }}"
                                    @selected(old('user_id', null) == $user->id)>{{ $user->first_name.' '.$user->last_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="user_name" :label="__('Player Name')" :value="old('user_name')" required />

                <x-field name="event_date" :label="__('Event Date')" type="date" :value="old('event_date')" required />

                <x-field name="amount" :label="__('Amount')" type="number" :value="old('amount')" required />

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Save') }}</x-btn>

                    <x-btn variant="ghost" :href="route('poker.venue-points.index')">{{ __('Cancel') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
