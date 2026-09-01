<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Edit Points Structure Entry')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.points-structure.update', $points_structure) }}" class="l-stack">
                @csrf
        @method('PATCH')

                <x-field name="place" :label="__('Place')" type="number" :value="old('place', $points_structure->place)" required autofocus />

                <x-field name="points" :label="__('Points')" type="number" :value="old('points', $points_structure->points)" required />

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Save') }}</x-btn>

                    <a class="link" href="{{ route('poker.points-structure.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
