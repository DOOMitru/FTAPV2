<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Register Player')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            {{-- No password field, deliberately. The account is created with an
                 unusable random password and the player sets their own through
                 a reset link, so no password ever passes through an
                 administrator's hands or their notes. --}}
            <p class="field__hint">
                {{ __('The player is approved immediately and receives a link to set their own password. They still verify their own email address.') }}
            </p>

            <form method="POST" action="{{ route('users.store') }}" class="l-stack">
                @csrf

                <x-field name="first_name" :label="__('First Name')" :value="old('first_name')" required autofocus />

                <x-field name="last_name" :label="__('Last Name')" :value="old('last_name')" required />

                <x-field name="nickname" :label="__('Nickname')" :value="old('nickname')" />

                <x-field name="email" type="email" :label="__('Email Address')" :value="old('email')" required />

                <div>
                    <label class="field__check" for="is_admin">
                        <input id="is_admin" type="checkbox" name="is_admin" value="1" {{ old('is_admin') ? 'checked' : '' }}>
                        <span>{{ __('Administrator') }}</span>
                    </label>

                    <x-input-error :messages="$errors->get('is_admin')" />
                </div>

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Register Player') }}</x-btn>

                    <x-btn variant="ghost" :href="route('users.index')">{{ __('Cancel') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
