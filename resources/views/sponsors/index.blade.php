<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Sponsors')">
            <x-slot name="actions">
                <x-btn variant="primary" :href="route('sponsors.create')">{{ __('Add Sponsor') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            {{-- Listed in ordered() sequence, the same scope the home page
                 uses, so this list is what the page will actually render. --}}
            <x-table>
                <x-slot name="head">
                    <th scope="col">{{ __('Logo') }}</th>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Tier') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($sponsors as $sponsor)
                    <tr>
                        {{-- The logo carries the link when there is a site to
                             reach, which is why the Website column is gone.

                             alt is not the same in both branches, and that is
                             not a slip: an image is the accessible NAME of a
                             link that contains nothing else, so an empty alt
                             here would leave a screen reader announcing a link
                             with no name at all. Unlinked it stays decorative,
                             because the name sits in the very next cell. --}}
                        <td>
                            @if ($sponsor->website_url)
                                <a class="sponsor-thumb-link" href="{{ $sponsor->website_url }}"
                                   target="_blank" rel="noopener noreferrer">
                                    <img class="sponsor-thumb" src="{{ $sponsor->logoUrl() }}"
                                         alt="{{ $sponsor->name }}">

                                    <span class="u-visually-hidden">{{ __('(opens in a new tab)') }}</span>
                                </a>
                            @else
                                <img class="sponsor-thumb" src="{{ $sponsor->logoUrl() }}" alt="">
                            @endif
                        </td>

                        <td>
                            <div class="entry__title">{{ $sponsor->name }}</div>

                            {{-- The stored value, not a prettied host: this is
                                 the screen an administrator checks the setting
                                 on, so it should show what was actually saved. --}}
                            @if ($sponsor->website_url)
                                <div class="entry__meta">
                                    <span>{{ $sponsor->website_url }}</span>
                                </div>
                            @endif
                        </td>

                        <td>
                            @if ($sponsor->isPremium())
                                <x-badge variant="primary">{{ __('Premium') }}</x-badge>
                            @else
                                <x-badge>{{ __('Regular') }}</x-badge>
                            @endif
                        </td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <x-action icon="edit" :label="__('Edit')" :href="route('sponsors.edit', $sponsor)" />

                                <form action="{{ route('sponsors.destroy', $sponsor) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? Their uploaded logo is deleted too, and that cannot be undone.', ['name' => $sponsor->name]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <x-action icon="delete" :label="__('Delete')" danger />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state :title="__('No sponsors yet.')">
                                {{ __('Sponsors added here appear on the home page. The wall is hidden entirely while this list is empty.') }}
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </x-card>
    </div>
</x-app-layout>
