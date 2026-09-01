<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Account')" :title="__('Profile')" />
    </x-slot>

    <div class="l-container l-stack">
        <x-card>@include('profile.partials.update-profile-information-form')</x-card>

        <x-card>@include('profile.partials.update-password-form')</x-card>

        <x-card>@include('profile.partials.delete-user-form')</x-card>
    </div>
</x-app-layout>
