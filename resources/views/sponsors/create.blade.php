<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Add Sponsor')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            @include('sponsors._form', ['sponsor' => null])
        </x-card>
    </div>
</x-app-layout>
