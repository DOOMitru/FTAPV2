<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('User Management')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('users.create')">{{ __('Register Player') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- Shown as well as emailed. MAIL_MAILER is log, so a link that is
             only sent reaches nobody; this keeps the Register Player button
             from producing an account no one can get into. It stays useful
             once a mailer exists, for when a player says it never arrived. --}}
        @if (session('invite_url'))
            <x-alert variant="info">
                {{ __('Send this link to the player so they can set a password. It expires and can only be used once.') }}

                <span class="u-mono">{{ session('invite_url') }}</span>
            </x-alert>
        @endif

        {{-- Rendered only when non-empty. A heading over an empty table is
             furniture: it costs attention on every visit and says nothing. --}}
        @if ($pending->isNotEmpty())
            <x-card :title="__('Awaiting approval')" flush>
                <x-table>
                    <x-slot name="head">
                        <th scope="col">{{ __('Name') }}</th>
                        <th scope="col">{{ __('Email') }}</th>
                        <th scope="col">{{ __('Registered') }}</th>
                        <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                    </x-slot>

                    @foreach ($pending as $candidate)
                        <tr>
                            <td>{{ $candidate->first_name }} {{ $candidate->last_name }}</td>

                            <td>{{ $candidate->email }}</td>

                            <td>{{ $candidate->created_at?->format('M d, Y') ?? '—' }}</td>

                            <td class="table__actions">
                                <div class="l-cluster l-cluster--end">
                                    <a class="link" href="{{ route('users.show', $candidate) }}">{{ __('View') }}</a>

                                    <form action="{{ route('users.approve', $candidate) }}" method="POST">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="link">{{ __('Approve') }}</button>
                                    </form>

                                    <form action="{{ route('users.reject', $candidate) }}" method="POST"
                                          data-confirm="{{ __('Reject :name? They keep their account but cannot enter tournaments.', ['name' => $candidate->first_name.' '.$candidate->last_name]) }}">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="link link--danger">{{ __('Reject') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        <x-card flush>
            <x-table>
                <x-slot name="head">
                    <th scope="col">{{ __('Photo') }}</th>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Nickname') }}</th>
                    <th scope="col">{{ __('Email') }}</th>
                    <th scope="col">{{ __('Role') }}</th>
                    <th scope="col">{{ __('Approval') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                {{-- @forelse, not @foreach. This page was the only index in the app
                     with no empty state at all: an admin list rendering as a blank
                     table says less than one that says it is empty. --}}
                @forelse ($users as $user)
                    <tr>
                        <td><x-avatar :user="$user" size="sm" decorative /></td>

                        <td>{{ $user->first_name }} {{ $user->last_name }}</td>

                        <td>{{ $user->nickname ?? '—' }}</td>

                        <td>{{ $user->email }}</td>

                        <td>
                            @if ($user->is_admin)
                                <x-badge variant="primary">{{ __('Admin') }}</x-badge>
                            @else
                                <x-badge>{{ __('Player') }}</x-badge>
                            @endif
                        </td>

                        {{-- What makes rejection reversible in fact rather
                             than in principle: a rejected account has left the
                             queue above, so without a status here there is no
                             route back to it. --}}
                        <td>
                            @if ($user->isApproved())
                                <x-badge variant="open">{{ __('Approved') }}</x-badge>
                            @elseif ($user->isPendingApproval())
                                <x-badge>{{ __('Pending') }}</x-badge>
                            @else
                                <x-badge variant="primary">{{ __('Rejected') }}</x-badge>
                            @endif
                        </td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <a class="link" href="{{ route('users.show', $user) }}">{{ __('View') }}</a>

                                <a class="link" href="{{ route('users.edit', $user) }}">{{ __('Edit') }}</a>

                                <form action="{{ route('users.destroy', $user) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $user->first_name.' '.$user->last_name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="link link--danger">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state :title="__('No users found.')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            @if ($users->hasPages())
                <div class="card__pager">{{ $users->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
