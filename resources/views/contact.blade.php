<x-public-layout>
    {{-- One column, not the two-column .p-split this used to be. The intro and
         the form are read in order rather than side by side, so each gets the
         whole width. --}}
    <div class="l-stack l-stack--loose">
        {{-- Uses <x-p-hero> like the other seven public pages instead of
             hand-setting .p-hero__title, which was borrowing a block's
             internals from outside the block.

             Still start-aligned, now that it spans the page rather than a
             column: the form beneath it reads from the left, and a centred
             heading over left-aligned fields sits oddly. --}}
        <x-p-hero suit="club" align="start"
                  :eyebrow="__('Connect with us')"
                  :title="__('Get in touch')"
                  :highlight="__('First to Act')">
            {{ __("Questions about joining, sponsoring, or something not working on the site — this reaches us either way.") }}
        </x-p-hero>

        <x-card class="p-raised">
            <div class="p-form-head">
                <img class="p-form-head__mark" src="{{ asset('images/hero_logo.png') }}" alt="">

                <div>
                    <h2 class="p-form-head__title">{{ __('Send us a message') }}</h2>
                    <p class="u-eyebrow">{{ __('Connect with the league') }}</p>
                </div>
            </div>

            @if (session('status'))
                <x-alert variant="success">{{ session('status') }}</x-alert>
            @endif

            <form action="{{ route('contact.store') }}" method="POST" class="l-stack">
                @csrf
                <x-honeypot />

                <div class="l-grid">
                    <x-field name="name" :label="__('Name')" required placeholder="{{ __('Your name') }}" />
                    <x-field name="email" type="email" :label="__('Email Address')" required placeholder="contact@example.com" />
                </div>

                <x-field name="topic" :label="__('Subject')">
                    <select class="field__control" name="topic" id="topic">
                        <option value="general">{{ __('General Inquiry') }}</option>
                        <option value="registration">{{ __('League Registration') }}</option>
                        <option value="partnership">{{ __('Commercial Partnership') }}</option>
                        <option value="support">{{ __('Technical Support') }}</option>
                    </select>
                </x-field>

                <x-field name="message" :label="__('Message')">
                    <textarea class="field__control" name="message" id="message" rows="4"
                              placeholder="{{ __('How can we help?') }}" required></textarea>
                </x-field>

                {{-- The width is the row's business, not the button's:
                     btn--block pinned it to 100% at every size. --}}
                <div class="p-form-actions">
                    <x-btn variant="primary">{{ __('Send') }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-public-layout>
