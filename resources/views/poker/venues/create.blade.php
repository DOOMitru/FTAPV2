<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="__('Add Venue')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.venues.store') }}" class="l-stack">
                @csrf

                <x-field name="name" :label="__('Name')" :value="old('name')" required autofocus />

                <x-field name="description" :label="__('Description')">
                    <textarea class="field__control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                </x-field>

                <x-field name="address" :label="__('Physical Address')" :value="old('address')" placeholder="e.g. 123 Poker Street, Las Vegas, NV" :hint="__('Optional: Providing an address will enable a Google Maps preview.')" />

                <div class="l-cluster l-cluster--end">
                    <x-btn variant="ghost" :href="route('poker.venues.index')">{{ __('Cancel') }}</x-btn>

                    <x-btn variant="primary">{{ __('Save') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
