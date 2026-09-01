<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Play')" :title="__('Add Tournament Result')" />
    </x-slot>

    @php
        // Built here rather than interpolated into a <script> block. json_encode
        // into a data attribute is escaped once by Blade and decoded once by the
        // browser; the old inline object literal put player names straight into
        // JavaScript source, where a name containing </script> ends the element.
        $tournamentOptions = $tournaments->mapWithKeys(fn ($t) => [
            $t->id => [
                'options' => $t->registrants
                    ->filter(fn ($r) => $r->user)
                    ->map(fn ($r) => [
                        'id' => (string) $r->user->id,
                        'firstName' => (string) $r->user->first_name,
                        'lastName' => (string) $r->user->last_name,
                        'nickname' => (string) $r->user->nickname,
                        'email' => (string) $r->user->email,
                    ])->values(),
                'exclude' => $t->results->pluck('user_id')->map(fn ($id) => (string) $id)->values(),
            ],
        ]);
    @endphp

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.results.store') }}" class="l-stack">
                @csrf

                <x-field name="tournament_id" :label="__('Tournament')">
                    <select class="field__control" name="tournament_id" id="tournament_id" required autofocus
                            data-dependent-source="{{ json_encode($tournamentOptions) }}"
                            data-dependent-target="#user_id"
                            data-dependent-placeholder="{{ __('Select Registered Player') }}">
                        <option value="">{{ __('Select Tournament') }}</option>

                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}"
                                    @selected(old('tournament_id', null) == $tournament->id)>
                                {{ $tournament->name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="points_structure_id" :label="__('Place & Points')"
                         :hint="__('Results are restricted to the defined points structure.')">
                    <select class="field__control" name="points_structure_id" id="points_structure_id" required>
                        <option value="">{{ __('Select Place') }}</option>

                        @foreach ($pointsStructures as $structure)
                            <option value="{{ $structure->id }}"
                                    @selected(old('points_structure_id', null) == $structure->id)>
                                {{ $structure->place }}{{ match ($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                                &middot; {{ number_format($structure->points) }} {{ __('pts') }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <div class="l-grid">
                    <x-field name="user_id" :label="__('Registered User')"
                             :hint="__('Only players registered for this tournament who do not already have a result.')">
                        <select class="field__control" name="user_id" id="user_id"
                                data-dependent-previous="{{ old('user_id', null) }}"
                                data-autofill='{"first":"#player_name","nick":"#player_nickname"}'>
                            <option value="">{{ __('Select Registered Player') }}</option>
                        </select>
                    </x-field>

                    <x-field name="player_name" :label="__('Player Name')" :value="old('player_name')" required />

                    <x-field name="player_nickname" :label="__('Player Nickname (Optional)')" :value="old('player_nickname')" />
                </div>

                <div class="l-cluster">
                    <x-btn variant="primary">{{ __('Save Result') }}</x-btn>

                    <a class="link" href="{{ route('poker.results.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
