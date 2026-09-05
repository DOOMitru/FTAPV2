<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Setup')" :title="__('Add Place to Points Structure')">
            {{-- In the header, not beside Save. This form returns to itself
                 after every save, so leaving is a different kind of act from
                 the entry being typed -- and the header is where this dashboard
                 already puts a page's own actions.

                 "Back", not "Cancel": by the second entry there is nothing left
                 to cancel, because the work is already saved. --}}
            <x-slot name="actions">
                <x-btn variant="ghost" :href="route('poker.points-structure.index')">{{ __('Back') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        {{-- The form comes back to itself after every save, so this is the only
             place the confirmation can land. Without it a successful entry and
             a form that did nothing look identical. --}}
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('poker.points-structure.store') }}" class="l-stack">
                @csrf

                {{-- Inlined: a fixed figure beside the one field there is
                     to fill in. The structure is a ladder from first place
                     down, so the next entry is always one deeper than the
                     deepest -- there is nothing here to decide. --}}
                <div class="field-row">
                    <div>
                        <span class="field__label">{{ __('Place') }}</span>
                        <p class="field__static">{{ \Illuminate\Support\Number::ordinal($nextPlace) }}</p>
                    </div>

                    <div class="field-row__grow">
                        <x-field name="points" :label="__('Points')" type="number" min="1"
                                 :value="old('points')"
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

                        <div class="l-cluster l-cluster--end">
                            <x-btn variant="primary">{{ __('Save') }}</x-btn>
                        </div>
                    </div>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
