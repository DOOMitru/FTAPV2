<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Edit Sponsor')" />
    </x-slot>

    <div class="l-container l-stack">
        <x-card :title="__('Current Logo')">
            <img class="sponsor-preview" src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}">
        </x-card>

        <x-card>
            @include('sponsors._form', ['sponsor' => $sponsor])
        </x-card>
    </div>
</x-app-layout>
