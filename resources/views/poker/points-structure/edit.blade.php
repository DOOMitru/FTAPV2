<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Edit Points Structure Entry')" />
    </x-slot>

    <div class="l-container">
        <x-card>
            <form method="POST" action="{{ route('poker.points-structure.update', $points_structure) }}" class="l-stack">
                @csrf
                @method('PATCH')

                {{-- Inlined: a fixed figure beside the one field there is
                     to fill in. Moving a place would collide with another or
                     leave a hole in the ladder; what an entry is worth is the
                     only thing about it that changes. --}}
                <div class="field-row">
                    <div>
                        <span class="field__label">{{ __('Place') }}</span>
                        <p class="field__static">{{ \Illuminate\Support\Number::ordinal($points_structure->place) }}</p>
                    </div>

                    <div class="field-row__grow">
                        <x-field name="points" :label="__('Points')" type="number" min="1"
                                 :value="old('points', $points_structure->points)"
                                 :hint="__('Must be at least 1. A place worth nothing is a place left out of the structure.')"
                                 required autofocus />
                    </div>

                    {{-- On the row, not under it: two controls and their
                         actions are one line's worth of form. The empty label
                         reserves the space the labels beside it take, so the
                         buttons line up with the inputs rather than with the
                         words above them. --}}
                    <div class="field-row__actions">
                        <span class="field__label" aria-hidden="true">&nbsp;</span>

                        <div class="l-cluster">
                            <x-btn variant="primary">{{ __('Save') }}</x-btn>

                            <x-btn variant="ghost" :href="route('poker.points-structure.index')">{{ __('Cancel') }}</x-btn>
                        </div>
                    </div>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
