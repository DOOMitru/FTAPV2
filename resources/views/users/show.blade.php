<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')"
                       :title="__('User Details').': '.$user->first_name.' '.$user->last_name">
            <x-slot name="actions">
                <x-btn variant="ghost" :href="route('users.index')">{{ __('Back to List') }}</x-btn>
                <x-btn variant="primary" :href="route('users.edit', $user)">{{ __('Edit User') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-sidebar">
        <x-card :title="__('Personal Information')">
            <dl class="rows">
                <div class="row">
                    <dt class="row__label">{{ __('Name') }}</dt>
                    <dd class="row__value">{{ $user->first_name }} {{ $user->last_name }}</dd>
                </div>

                <div class="row">
                    <dt class="row__label">{{ __('Nickname') }}</dt>
                    <dd class="row__value">{{ $user->nickname ?? '—' }}</dd>
                </div>

                <div class="row">
                    <dt class="row__label">{{ __('Email') }}</dt>
                    <dd class="row__value">{{ $user->email }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('Account Details')">
            <div class="field__media">
                <x-avatar :user="$user" size="lg" decorative />

                <div>
                    <div class="entry__title">{{ $user->display_name }}</div>

                    <div class="entry__meta">
                        <span>
                            @if ($user->is_admin)
                                {{ __('Administrator') }}
                            @else
                                {{ __('Player') }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <dl class="rows">
                <div class="row">
                    <dt class="row__label">{{ __('Role') }}</dt>
                    <dd class="row__value">
                        @if ($user->is_admin)
                            <x-badge variant="primary">{{ __('Admin') }}</x-badge>
                        @else
                            <x-badge>{{ __('Player') }}</x-badge>
                        @endif
                    </dd>
                </div>

                <div class="row">
                    <dt class="row__label">{{ __('Member Since') }}</dt>
                    <dd class="row__value">{{ $user->created_at?->format('M d, Y') ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>
    </div>
</x-app-layout>
