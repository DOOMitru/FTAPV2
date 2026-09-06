<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')"
                       :title="__('Edit User').': '.$user->first_name.' '.$user->last_name" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('users.update', $user) }}" class="l-stack">
                @csrf
                @method('PATCH')

                <div class="l-grid">
                    <x-field name="first_name" :label="__('First Name')"
                             :value="old('first_name', $user->first_name)" required autofocus
                             autocomplete="given-name" />

                    <x-field name="last_name" :label="__('Last Name')"
                             :value="old('last_name', $user->last_name)" required
                             autocomplete="family-name" />
                </div>

                <x-field name="nickname" :label="__('Nickname')"
                         :value="old('nickname', $user->nickname)" autocomplete="nickname" />

                <x-field name="email" type="email" :label="__('Email')"
                         :value="old('email', $user->email)" required autocomplete="username" />

                <div>
                    {{-- The hidden 0 must come first and must stay: an unchecked
                         checkbox submits nothing, so without it clearing this box
                         would leave is_admin untouched rather than false. It
                         carries no id -- the label points at the checkbox, which
                         is what a person actually clicks. --}}
                    <input type="hidden" name="is_admin" value="0">

                    <label class="field__check" for="is_admin">
                        <input id="is_admin" type="checkbox" name="is_admin" value="1"
                               {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                        <span>{{ __('Administrator Access') }}</span>
                    </label>

                    <x-input-error :messages="$errors->get('is_admin')" />
                </div>

                <div class="l-cluster l-cluster--end">
                    <x-btn variant="ghost" :href="route('users.index')">{{ __('Cancel') }}</x-btn>

                    <x-btn variant="primary">{{ __('Update User') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
