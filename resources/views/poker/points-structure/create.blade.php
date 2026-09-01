<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Add Place to Points Structure')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.points-structure.store') }}" class="l-stack">
                @csrf

                <x-field name="place" :label="__('Place')" type="number" :value="old('place')" required autofocus />

                <x-field name="points" :label="__('Points')" type="number" :value="old('points')" required />

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Save') }}</x-btn>

                    <a class="link" href="{{ route('poker.points-structure.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
