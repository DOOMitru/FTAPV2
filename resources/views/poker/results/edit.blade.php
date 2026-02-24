<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Tournament Result') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('poker.results.update', $result) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="tournament_id" :value="__('Tournament')" />
                            <select id="tournament_id" name="tournament_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required onchange="updateUserList()">
                                @foreach ($tournaments as $tournament)
                                    <option value="{{ $tournament->id }}" {{ old('tournament_id', $result->tournament_id) == $tournament->id ? 'selected' : '' }}>{{ $tournament->name }} ({{ $tournament->start_time }})</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('tournament_id')" />
                        </div>

                        <div>
                            <x-input-label for="points_structure_id" :value="__('Place & Points')" />
                            <select id="points_structure_id" name="points_structure_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                <option value="">{{ __('Select Place') }}</option>
                                @foreach ($pointsStructures as $structure)
                                    @php
                                        $isSelected = (old('points_structure_id') == $structure->id) || 
                                                      ($result->place == $structure->place && $result->points == $structure->points);
                                    @endphp
                                    <option value="{{ $structure->id }}" {{ $isSelected ? 'selected' : '' }}>
                                        {{ $structure->place }}{{ match($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }} Place - {{ number_format($structure->points) }} Points
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('points_structure_id')" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Results are restricted to the defined Points Structure.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="user_id" :value="__('Registered User')" />
                                <select id="user_id" name="user_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required
                                    onchange="
                                        const selected = this.options[this.selectedIndex];
                                        if(selected && selected.value) { 
                                            document.getElementById('player_name').value = `${selected.dataset.firstName} ${selected.dataset.lastName}`.trim();
                                            document.getElementById('player_nickname').value = selected.dataset.nickname || '';
                                        }
                                    ">
                                    <option value="">{{ __('Select Registered Player') }}</option>
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
                                <p class="mt-1 text-xs text-info">{{ __('Only players registered for this tournament who do NOT have a result yet are shown.') }}</p>
                            </div>

                            <div>
                                <x-input-label for="player_name" :value="__('Player Name')" />
                                <x-text-input id="player_name" name="player_name" type="text" class="mt-1 block w-full" :value="old('player_name', $result->player_name)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('player_name')" />
                            </div>

                            <div>
                                <x-input-label for="player_nickname" :value="__('Player Nickname (Optional)')" />
                                <x-text-input id="player_nickname" name="player_nickname" type="text" class="mt-1 block w-full" :value="old('player_nickname', $result->player_nickname)" />
                                <x-input-error class="mt-2" :messages="$errors->get('player_nickname')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update Result') }}</x-primary-button>
                            <a href="{{ route('poker.results.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const tournamentData = {
            @foreach ($tournaments as $tournament)
                "{{ $tournament->id }}": {
                    registrants: [
                        @foreach ($tournament->registrants as $registrant)
                            {
                                id: "{{ $registrant->user->id }}",
                                firstName: "{{ $registrant->user->first_name }}",
                                lastName: "{{ $registrant->user->last_name }}",
                                nickname: "{{ $registrant->user->nickname }}",
                                email: "{{ $registrant->user->email }}"
                            },
                        @endforeach
                    ],
                    resultsUserIds: [
                        @foreach ($tournament->results as $res)
                            @if ($res->id !== $result->id)
                                "{{ $res->user_id }}",
                            @endif
                        @endforeach
                    ]
                },
            @endforeach
        };

        function updateUserList() {
            const tournamentId = document.getElementById('tournament_id').value;
            const userSelect = document.getElementById('user_id');
            const data = tournamentData[tournamentId] || { registrants: [], resultsUserIds: [] };
            
            userSelect.innerHTML = '<option value="">{{ __("Select Registered Player") }}</option>';
            
            data.registrants.forEach(player => {
                // Filter out users who already have a result, EXCEPT the currently edited user if in the same tournament
                if (!data.resultsUserIds.includes(player.id)) {
                    const option = document.createElement('option');
                    option.value = player.id;
                    option.text = `${player.firstName} ${player.lastName} (${player.email})`;
                    option.dataset.firstName = player.firstName;
                    option.dataset.lastName = player.lastName;
                    option.dataset.nickname = player.nickname;
                    userSelect.appendChild(option);
                }
            });

            // Re-select current value or old value
            const currentUserId = "{{ old('user_id', $result->user_id) }}";
            if (currentUserId) {
                userSelect.value = currentUserId;
            }
        }

        // Initialize on load
        window.addEventListener('load', updateUserList);
    </script>
</x-app-layout>
