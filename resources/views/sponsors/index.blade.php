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
                    <th scope="col">{{ __('Website') }}</th>
                    <th scope="col" class="table__num">{{ __('Order') }}</th>
                    <th scope="col" class="table__actions">{{ __('Actions') }}</th>
                </x-slot>

                @forelse ($sponsors as $sponsor)
                    <tr>
                        <td><img class="sponsor-thumb" src="{{ $sponsor->logoUrl() }}" alt=""></td>

                        <td>{{ $sponsor->name }}</td>

                        <td>
                            @if ($sponsor->isPremium())
                                <x-badge variant="primary">{{ __('Premium') }}</x-badge>
                            @else
                                <x-badge>{{ __('Regular') }}</x-badge>
                            @endif
                        </td>

                        <td>
                            @if ($sponsor->website_url)
                                <a class="link" href="{{ $sponsor->website_url }}"
                                   target="_blank" rel="noopener noreferrer">{{ __('Visit') }}</a>
                            @else
                                &mdash;
                            @endif
                        </td>

                        <td class="table__num">{{ $sponsor->sort_order }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <a class="link" href="{{ route('sponsors.edit', $sponsor) }}">{{ __('Edit') }}</a>

                                <form action="{{ route('sponsors.destroy', $sponsor) }}" method="POST"
                                      data-confirm="{{ __('Delete :name? Their uploaded logo is deleted too, and that cannot be undone.', ['name' => $sponsor->name]) }}">
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
