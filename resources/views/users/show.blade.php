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

    {{-- Its own container, above the sidebar grid rather than inside it: a
         direct child of .l-sidebar becomes one of its two columns, so an alert
         placed there would render as a sidebar rather than as a notice. --}}
    @if (session('status') || session('error') || session('invite_url') || session('verification_url'))
        <div class="l-container l-stack">
            @if (session('status'))
                <x-alert variant="success">{{ session('status') }}</x-alert>
            @endif

            @if (session('error'))
                <x-alert variant="danger">{{ session('error') }}</x-alert>
            @endif

            {{-- Shown as well as sent. MAIL_MAILER is log, so a link that is
                 only emailed reaches nobody; these stay useful once a mailer
                 exists, for when a player says it never arrived. --}}
            @foreach (['invite_url' => __('Password link'), 'verification_url' => __('Verification link')] as $key => $label)
                @if (session($key))
                    <x-alert variant="info">
                        {{ $label }} &mdash; {{ __('expires, and can only be used once.') }}

                        <span class="u-mono">{{ session($key) }}</span>
                    </x-alert>
                @endif
            @endforeach
        </div>
    @endif

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
                <x-monogram :user="$user" size="lg" decorative />

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

            <div class="l-stack">
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

                <div class="row">
                    <dt class="row__label">{{ __('Approval') }}</dt>
                    <dd class="row__value">
                        @if ($user->isApproved())
                            <x-badge variant="open">{{ __('Approved') }}</x-badge>
                        @elseif ($user->isPendingApproval())
                            <x-badge>{{ __('Pending') }}</x-badge>
                        @else
                            <x-badge variant="primary">{{ __('Rejected') }}</x-badge>
                        @endif
                    </dd>
                </div>

                @if ($user->approval_decided_at)
                    <div class="row">
                        <dt class="row__label">{{ __('Decided') }}</dt>
                        <dd class="row__value">
                            {{ $user->approval_decided_at->format('M d, Y') }}
                            {{-- Null for the accounts grandfathered by the
                                 migration: no person made that decision, so
                                 naming one would be a false audit trail. --}}
                            @if ($decidedBy)
                                {{ __('by') }} {{ $decidedBy->first_name }} {{ $decidedBy->last_name }}
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            {{-- This page, not only the queue, is the full control surface.
                 A rejected account has left the queue, so this is the only
                 place the decision can be reversed.

                 The card content is an l-stack so the gap lands BETWEEN the
                 <dl> and this cluster. Putting l-stack ON the cluster would
                 have been wrong: it sets margin-block-start on CHILDREN, so in
                 a flex row it offsets each button instead of spacing the
                 group. --}}
            <div class="l-cluster">
                <form action="{{ route('users.invite', $user) }}" method="POST">
                    @csrf

                    {{-- Offered unconditionally: whether this account has ever
                         set a password is not knowable from the schema. Every
                         account has a hash, and the random one a new player
                         starts with is indistinguishable from a chosen one. --}}
                    <x-btn variant="ghost" type="submit">{{ __('Send password link') }}</x-btn>
                </form>

                @unless ($user->hasVerifiedEmail())
                    {{-- Only while it can do something. An action that cannot
                         act should not be drawn. --}}
                    <form action="{{ route('users.verification', $user) }}" method="POST">
                        @csrf

                        <x-btn variant="ghost" type="submit">{{ __('Send verification link') }}</x-btn>
                    </form>
                @endunless

                @unless ($user->isApproved())
                    <form action="{{ route('users.approve', $user) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <x-btn variant="primary" type="submit">{{ __('Approve') }}</x-btn>
                    </form>
                @endunless

                @if ($user->isApproved())
                    <form action="{{ route('users.reject', $user) }}" method="POST"
                          data-confirm="{{ __('Reject :name? They keep their account but cannot enter tournaments.', ['name' => $user->first_name.' '.$user->last_name]) }}">
                        @csrf
                        @method('PATCH')

                        <x-btn variant="danger" type="submit">{{ __('Reject') }}</x-btn>
                    </form>
                @endif
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
