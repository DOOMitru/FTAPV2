<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Schedule Tournament')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.tournaments.store') }}" class="l-stack">
                @csrf

                <x-field name="name" :label="__('Name')" :value="old('name')" required autofocus />

                <x-field name="description" :label="__('Description')">
                    <textarea class="field__control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                </x-field>

                <div class="l-grid">
                    <x-field name="scheduled_at" type="datetime-local"
                             :label="__('Registration Closes (Scheduled At)')"
                             :value="old('scheduled_at')" required />

                    <x-field name="start_time" type="datetime-local" :label="__('Start Date & Time')"
                             :value="old('start_time')" required />
                </div>

                <div class="l-grid">
                {{-- Read-only on create: the controller assigns the current
                     season, so offering a choice would imply one exists. --}}
                <div>
                    <span class="field__label">{{ __('Season') }}</span>

                    <p class="row__value">{{ $currentSeason->name ?? __('No Active Season') }}</p>
                </div>

                    <x-field name="venue_id" :label="__('Venue')">
                        <select class="field__control" name="venue_id" id="venue_id" required>
                            <option value="">{{ __('Select Venue') }}</option>

                            @foreach ($venues as $venue)
                                <option value="{{ $venue->id }}"
                                        @selected(old('venue_id', null) == $venue->id)>{{ $venue->name }}</option>
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
