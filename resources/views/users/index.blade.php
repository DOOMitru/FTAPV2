<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('User Management')" />
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            <x-table>
                <x-slot name="head">
                    <th scope="col">{{ __('Photo') }}</th>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Nickname') }}</th>
                    <th scope="col">{{ __('Email') }}</th>
                    <th scope="col">{{ __('Role') }}</th>
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
                        <td colspan="6">
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
