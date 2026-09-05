<x-public-layout>
    <x-p-hero suit="heart" :title="__('Our Mission')" :highlight="__('Mission')" />

    <div class="p-split">
        <img class="p-figure" src="{{ asset('images/hero_logo.png') }}" alt="">

        <div class="l-prose">
            <p class="p-lede"><strong>First to Act Poker</strong> runs free-to-play poker nights for social players in Regina. No buy-in, no experience required, and someone will happily explain the rules at the table.</p>

            <p>We run events every week. Points carry across the whole season, and every player who hits the season's qualification targets plays the finale.</p>

            <p>The arrangement works both ways: host venues meet new customers, and local businesses reach a room full of regulars. We also run charity tournaments.</p>
        </div>
    </div>

    <hr class="p-rule">

    <section id="become-a-sponsor" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Become a Sponsor') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

        <div class="p-split">
            <div class="l-stack">
                <div class="l-prose">
                    <p class="p-lede"><strong>First to Act Poker</strong> is a Regina poker league. We play at several venues around the city, most weeks of the year, and the games are free to enter.</p>

                    <p>We are inviting local businesses to partner with us as league sponsors. One annual fee puts your name in front of every player, at every table, all season. Your logo goes on the posters in every partner venue, and on this site.</p>
                </div>

                <section class="p-panel p-panel--accent">
                    <div class="p-panel__glow" aria-hidden="true"></div>

                    <h3 class="p-panel__eyebrow">{{ __('Where does the money go?') }}</h3>

                    {{-- Two paragraphs rather than one, for the same reason the
                         sponsorship notes below are two: they are separate
                         sentences doing different jobs -- where the money goes,
                         then what that buys -- and run together they read as one
                         long line with a full stop in the middle of it. --}}
                    <div class="p-panel__text">
                        <p>Every dollar of that fee goes into the prize pool for the Season Finale.</p>

                        <p>That is what keeps the games free to play.</p>
                    </div>
                </section>

                <div>
                    <h3 class="p-section-head__title">{{ __('Sponsorship Benefits') }}</h3>

                    <ul class="p-benefits">
                        <li class="p-benefit">
                            <svg class="p-benefit__tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            <span>Your logo on the posters in every partner venue.</span>
                        </li>

                        <li class="p-benefit">
                            <svg class="p-benefit__tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            <span>Your logo and a link on this site.</span>
                        </li>

                        <li class="p-benefit">
                            <svg class="p-benefit__tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            <span>Mentions from the front of the room during league nights.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <x-card class="p-raised">
                <div class="p-form-head">
                    <div>
                        <h2 class="p-form-head__title">{{ __('Sponsorship Inquiry') }}</h2>
                        <p class="u-eyebrow">{{ __('Partner with the league') }}</p>
                    </div>
                </div>

                {{-- Two paragraphs rather than a <br>: they are two sentences
                     doing different jobs -- the hook, then the instruction. --}}
                <div class="p-form-head__notes">
                    <p>{{ __('Ready to grow your business while supporting local poker?') }}</p>

                    <p>{{ __("Send us an inquiry below and we'll get in touch to finalize your sponsorship.") }}</p>
                </div>

                @if (session('status'))
                    <x-alert variant="success">{{ session('status') }}</x-alert>
                @endif

                <form action="{{ route('contact.store') }}" method="POST" class="l-stack">
                    @csrf
                    <input type="hidden" name="topic" value="sponsorship">
                    <x-honeypot id="sponsor_company" />

                    <x-field name="name" :label="__('Business or Representative Name')" required
                             placeholder="{{ __('e.g. Ace High Beverages') }}" />

                    <x-field name="email" type="email" :label="__('Email Address')" required
                             placeholder="contact@example.com" />

                    <x-field name="message" :label="__('Message')">
                        <textarea class="field__control" name="message" id="message" rows="4"
                                  placeholder="{{ __('How can we help highlight your brand?') }}" required></textarea>
                    </x-field>

                    {{-- The width is the row's business, not the button's --
                         same as the contact form. --}}
                    <div class="p-form-actions">
                        <x-btn variant="primary">{{ __('Send Inquiry') }}</x-btn>
                    </div>
                </form>
            </x-card>
        </div>
    </section>
</x-public-layout>
