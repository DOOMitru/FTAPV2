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
                <x-table stacked>
                    <x-slot name="head">
                        <th scope="col">{{ __('Name') }}</th>
                        <th scope="col">{{ __('Email') }}</th>
                        <th scope="col">{{ __('Registered') }}</th>
                        <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                    </x-slot>

                    @foreach ($pending as $candidate)
                        <tr>
                            <td data-label="{{ __('Name') }}">{{ $candidate->first_name }} {{ $candidate->last_name }}</td>

                            <td data-label="{{ __('Email') }}">{{ $candidate->email }}</td>

                            <td data-label="{{ __('Registered') }}">{{ $candidate->created_at?->format('M d, Y') ?? '—' }}</td>

                            <td class="table__actions">
                                <div class="l-cluster l-cluster--end">
                                    <x-action icon="view" :label="__('View')" :href="route('users.show', $candidate)" />

                                    <form action="{{ route('users.approve', $candidate) }}" method="POST">
                                        @csrf
                                        @method('PATCH')

                                        <x-action icon="approve" :label="__('Approve')" />
                                    </form>

                                    <form action="{{ route('users.reject', $candidate) }}" method="POST"
                                          data-confirm="{{ __('Reject :name? They keep their account but cannot enter tournaments.', ['name' => $candidate->first_name.' '.$candidate->last_name]) }}">
                                        @csrf
                                        @method('PATCH')

                                        <x-action icon="reject" :label="__('Reject')" danger />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        {{-- A plain GET form: the term lives in the URL, so a filtered list can
             be linked, bookmarked and paged without any JavaScript. Above the
             table rather than in the card header, because the header is a
             non-wrapping flex row and a 16rem control plus two buttons
             overruns it on a narrow screen. --}}
        <form method="GET" action="{{ route('users.index') }}" class="search">
            <label class="u-visually-hidden" for="search">{{ __('Search users') }}</label>

            <input id="search" name="search" type="search" value="{{ $search }}"
                   class="field__control field__control--inline"
                   placeholder="{{ __('Name, nickname or email') }}">

            {{-- Icon-only, so each carries its name in a visually-hidden span:
                 the accessible name then comes from content, which is
                 translated with the rest of the page. title gives a sighted
                 mouse user the same word on hover. --}}
            <x-btn variant="ghost" type="submit" class="btn--icon" :title="__('Search')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>

                <span class="u-visually-hidden">{{ __('Search') }}</span>
            </x-btn>

            {{-- Only when there is something to clear. A permanent Clear on an
                 unfiltered list is a button that does nothing. --}}
            @if ($search !== '')
                <x-btn variant="ghost" class="btn--icon" :title="__('Clear search')"
                       :href="route('users.index')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>

                    <span class="u-visually-hidden">{{ __('Clear search') }}</span>
                </x-btn>
            @endif
        </form>

        <x-card flush>
            <x-table stacked>
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
                        <td class="table__thumb"><x-avatar :user="$user" size="sm" decorative /></td>

                        <td data-label="{{ __('Name') }}">{{ $user->first_name }} {{ $user->last_name }}</td>

                        <td data-label="{{ __('Nickname') }}">{{ $user->nickname ?? '—' }}</td>

                        <td data-label="{{ __('Email') }}">{{ $user->email }}</td>

                        <td data-label="{{ __('Role') }}">
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
                        <td data-label="{{ __('Approval') }}">
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
                                <x-action icon="view" :label="__('View')" :href="route('users.show', $user)" />

                                <x-action icon="edit" :label="__('Edit')" :href="route('users.edit', $user)" />

                                <form action="{{ route('users.destroy', $user) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $user->first_name.' '.$user->last_name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-action icon="delete" :label="__('Delete')" danger />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            @if ($search !== '')
                                <x-empty-state :title="__('No users match :term.', ['term' => '“'.$search.'”'])">
                                    {{ __('Searches look at first and last name, nickname and email.') }}
                                </x-empty-state>
                            @else
                                <x-empty-state :title="__('No users found.')" />
                            @endif
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
