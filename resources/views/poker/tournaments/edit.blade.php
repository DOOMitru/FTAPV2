<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Edit Tournament')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.tournaments.update', $tournament) }}" class="l-stack">
                @csrf
        @method('PATCH')

                <x-field name="name" :label="__('Name')" :value="old('name', $tournament->name)" required autofocus />

                <x-field name="description" :label="__('Description')">
                    <textarea class="field__control" name="description" id="description" rows="3">{{ old('description', $tournament->description) }}</textarea>
                </x-field>

                <div class="l-grid">
                    <x-field name="scheduled_at" type="datetime-local"
                             :label="__('Registration Closes (Scheduled At)')"
                             :value="old('scheduled_at', $tournament->scheduled_at?->format('Y-m-d\TH:i'))" required />

                    <x-field name="start_time" type="datetime-local" :label="__('Start Date & Time')"
                             :value="old('start_time', $tournament->start_time?->format('Y-m-d\TH:i'))" required />
                </div>

                <div class="l-grid">
                <x-field name="season_id" :label="__('Season')">
                    <select class="field__control" name="season_id" id="season_id" required>
                        @foreach ($seasons as $season)
                            <option value="{{ $season->id }}"
                                    @selected(old('season_id', $tournament->season_id) == $season->id)>{{ $season->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                    <x-field name="venue_id" :label="__('Venue')">
                        <select class="field__control" name="venue_id" id="venue_id" required>
                            <option value="">{{ __('Select Venue') }}</option>

                            @foreach ($venues as $venue)
                                <option value="{{ $venue->id }}"
                                        @selected(old('venue_id', $tournament->venue_id) == $venue->id)>{{ $venue->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <div class="l-cluster l-cluster--end">
                    <x-btn variant="ghost" :href="route('poker.tournaments.index')">{{ __('Cancel') }}</x-btn>

                    <x-btn variant="primary">{{ __('Save') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
