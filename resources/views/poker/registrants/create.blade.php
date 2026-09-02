<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Add Tournament Registrant')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.registrants.store') }}" class="l-stack">
                @csrf

                <x-field name="tournament_id" :label="__('Tournament')">
                    <select class="field__control" name="tournament_id" id="tournament_id" required autofocus>
                        <option value="">{{ __('Select Tournament') }}</option>

                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}"
                                    @selected(old('tournament_id', null) == $tournament->id)>{{ $tournament->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="registered_at" :label="__('Registration Date & Time')" type="datetime-local" :value="old('registered_at', now()->format('Y-m-d\TH:i'))" required />

                <x-field name="user_id" :label="__('Linked User')">
                    <select class="field__control" name="user_id" id="user_id" required data-autofill='{"first":"#player_name","nick":"#player_nickname"}'>
                        <option value="">{{ __('Select User') }}</option>

                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" data-first-name="{{ $user->first_name }}"
                                    data-last-name="{{ $user->last_name }}"
                                    data-nickname="{{ $user->nickname }}"
                                    @selected(old('user_id', null) == $user->id)>{{ $user->first_name.' '.$user->last_name.' ('.$user->email.')' }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="player_name" :label="__('Player Name')" :value="old('player_name')" required />

                <x-field name="player_nickname" :label="__('Player Nickname (Optional)')" :value="old('player_nickname')" />

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Add Registrant') }}</x-btn>

                    <x-btn variant="ghost" :href="route('poker.registrants.index')">{{ __('Cancel') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
