<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Edit Tournament Registrant')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.registrants.update', $registrant) }}" class="l-stack">
                @csrf
        @method('PATCH')

                <x-field name="tournament_id" :label="__('Tournament')">
                    <select class="field__control" name="tournament_id" id="tournament_id" required>
                        <option value="">{{ __('Select Tournament') }}</option>

                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}"
                                    @selected(old('tournament_id', $registrant->tournament_id) == $tournament->id)>{{ $tournament->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="registered_at" :label="__('Registration Date & Time')" type="datetime-local" :value="old('registered_at', \Illuminate\Support\Carbon::parse($registrant->registered_at)->format('Y-m-d\TH:i'))" required />

                <x-field name="user_id" :label="__('Linked User')">
                    <select class="field__control" name="user_id" id="user_id" required data-autofill='{"first":"#player_name","nick":"#player_nickname"}'>
                        <option value="">{{ __('Select User') }}</option>

                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" data-first-name="{{ $user->first_name }}"
                                    data-last-name="{{ $user->last_name }}"
                                    data-nickname="{{ $user->nickname }}"
                                    @selected(old('user_id', $registrant->user_id) == $user->id)>{{ $user->first_name.' '.$user->last_name.' ('.$user->email.')' }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="player_name" :label="__('Player Name')" :value="old('player_name', $registrant->player_name)" required />

                <x-field name="player_nickname" :label="__('Player Nickname (Optional)')" :value="old('player_nickname', $registrant->player_nickname)" />

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Save') }}</x-btn>

                    <a class="link" href="{{ route('poker.registrants.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
