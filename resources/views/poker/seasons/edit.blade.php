<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Edit Poker Season')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.seasons.update', $season) }}" class="l-stack">
                @csrf
        @method('PATCH')

                <x-field name="name" :label="__('Name')" :value="old('name', $season->name)" required autofocus />

                <x-field name="description" :label="__('Description')">
                    <textarea class="field__control" name="description" id="description" rows="3">{{ old('description', $season->description) }}</textarea>
                </x-field>

                {{-- ->format('Y-m-d'): the model casts these to Carbon, which
                     stringifies as "2026-05-01 00:00:00". An <input type="date">
                     accepts YYYY-MM-DD and renders anything else as BLANK, so
                     editing a season silently offered to wipe its dates. --}}
                <x-field name="start_date" :label="__('Start Date')" type="date" :value="old('start_date', $season->start_date?->format('Y-m-d'))" required />

                <x-field name="end_date" :label="__('End Date')" type="date" :value="old('end_date', $season->end_date?->format('Y-m-d'))" required />

                <div>
                    <label class="field__check" for="is_current">
                        <input id="is_current" type="checkbox" name="is_current" value="1" {{ old('is_current', $season->is_current) ? 'checked' : '' }}>
                        <span>{{ __('Is Current Season') }}</span>
                    </label>

                    <x-input-error :messages="$errors->get('is_current')" />
                </div>

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Save') }}</x-btn>

                    <a class="link" href="{{ route('poker.seasons.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
