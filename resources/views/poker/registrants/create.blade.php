<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Add Tournament Registrant')">
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('poker.registrants.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="tournament_id" :value="__('Tournament')" />
                            <select id="tournament_id" name="tournament_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required autofocus>
                                <option value="">{{ __('Select Tournament') }}</option>
                                @foreach ($tournaments as $tournament)
                                    <option value="{{ $tournament->id }}" {{ old('tournament_id') == $tournament->id ? 'selected' : '' }}>{{ $tournament->name }} ({{ $tournament->start_time }})</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('tournament_id')" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="registered_at" :value="__('Registration Date & Time')" />
                                <x-text-input id="registered_at" name="registered_at" type="datetime-local" class="mt-1 block w-full" :value="old('registered_at', now()->format('Y-m-d\TH:i'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('registered_at')" />
                            </div>

                            <div>
                                <x-input-label for="user_id" :value="__('Linked User')" />
                                <select id="user_id" name="user_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required
                                    onchange="
                                        const selected = this.options[this.selectedIndex];
                                        if(selected.value) { 
                                            document.getElementById('player_name').value = `${selected.dataset.firstName} ${selected.dataset.lastName}`.trim();
                                            document.getElementById('player_nickname').value = selected.dataset.nickname || '';
                                        }
                                    ">
                                    <option value="">{{ __('Select User') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" 
                                            {{ old('user_id') == $user->id ? 'selected' : '' }}
                                            data-first-name="{{ $user->first_name }}"
                                            data-last-name="{{ $user->last_name }}"
                                            data-nickname="{{ $user->nickname }}">
                                            {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="player_name" :value="__('Player Name')" />
                                <x-text-input id="player_name" name="player_name" type="text" class="mt-1 block w-full" :value="old('player_name')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('player_name')" />
                            </div>

                            <div>
                                <x-input-label for="player_nickname" :value="__('Player Nickname (Optional)')" />
                                <x-text-input id="player_nickname" name="player_nickname" type="text" class="mt-1 block w-full" :value="old('player_nickname')" />
                                <x-input-error class="mt-2" :messages="$errors->get('player_nickname')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Add Registrant') }}</x-primary-button>
                            <a href="{{ route('poker.registrants.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
