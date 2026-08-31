<x-public-layout>
    <div class="p-split">
        <div class="l-stack--loose">
            {{-- The page's hero, start-aligned inside a column rather than
                 centred across the page. Uses <x-p-hero> like the other seven
                 public pages instead of hand-setting .p-hero__title, which was
                 borrowing a block's internals from outside the block. --}}
            <x-p-hero align="start"
                      :eyebrow="__('Connect with us')"
                      :title="__('Join the First to Act Circle')"
                      :highlight="__('First to Act')">
                {{ __("Whether you're looking to join the league, discuss partnership opportunities, or need technical assistance, our stewards are ready to connect.") }}
            </x-p-hero>

            <div class="p-contacts">
                <div class="p-contact">
                    <span class="p-icon-tile">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </span>

                    <div>
                        <span class="p-contact__label">{{ __('Email Us') }}</span>
                        <span class="p-contact__value">steward@firsttoact.com</span>
                    </div>
                </div>

                <div class="p-contact">
                    {{-- Filled rather than stroked: the Facebook mark is a solid
                         glyph and outlining it does not read as the logo. --}}
                    <span class="p-icon-tile">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.5-3.91 3.77-3.91 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/>
                        </svg>
                    </span>

                    <div>
                        <span class="p-contact__label">{{ __('Facebook Page') }}</span>
                        <span class="p-contact__value">facebook.com/firsttoact</span>
                    </div>
                </div>
            </div>

        </div>

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

                <x-btn variant="primary" class="btn--block">{{ __('Send') }}</x-btn>
            </form>
        </x-card>
    </div>
</x-public-layout>
