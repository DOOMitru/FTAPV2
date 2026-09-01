<x-public-layout>
    <x-p-hero suit="heart" :title="__('Our Mission')" :highlight="__('Mission')" />

    <div class="p-split">
        <img class="p-figure" src="{{ asset('images/hero_logo.png') }}" alt="">

        <div class="l-prose">
            <p class="p-lede"><strong>First to Act Poker</strong> is dedicated to providing high-quality, free-to-play poker events for the social player in Regina. We believe that the excitement of Texas Hold'em should be accessible to everyone in a fun, safe, and competitive environment.</p>

            <p>We run weekly events where players can sharpen their skills, socialize with the local community, and accumulate points that lead toward exclusive special events and rewards.</p>

            <p>Our league creates a win-win ecosystem: host venues are introduced to new customers, and local businesses gain a platform for focused exposure. We also proudly host charity tournaments, ensuring that our love for the game also helps support meaningful causes.</p>
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
                    <p class="p-lede"><strong>First to Act Poker</strong> is a growing Regina-based start-up dedicated to hosting vibrant, community-focused free-to-play poker leagues. We are currently bringing the excitement of the game to multiple venues across the city, providing a fun and social environment for local players.</p>

                    <p>We are inviting local businesses to partner with us as league sponsors. For a modest annual contribution, your brand will gain consistent exposure to our dedicated and engaged community. This is a highly cost-effective way to advertise your business both on our official website and on our event posters displayed at all our partner venues.</p>
                </div>

                <section class="p-panel p-panel--accent">
                    <div class="p-panel__glow" aria-hidden="true"></div>

                    <h3 class="p-panel__eyebrow">{{ __('Where does the money go?') }}</h3>

                    <p class="p-panel__text">100% of sponsorship fees are pooled to fund a substantial grand prize for our annual Season Finale Tournament. By becoming a sponsor, you’re not just advertising—you’re directly supporting the Regina poker community and helping us reward the skill and sportsmanship of our top players.</p>
                </section>

                <div>
                    <h3 class="p-section-head__title">{{ __('Sponsorship Benefits') }}</h3>

                    <ul class="p-benefits">
                        <li class="p-benefit">
                            <svg class="p-benefit__tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            <span>High-visibility logo placement on our league posters in partner venues.</span>
                        </li>

                        <li class="p-benefit">
                            <svg class="p-benefit__tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            <span>Dedicated brand logo and backlink on our official website.</span>
                        </li>

                        <li class="p-benefit">
                            <svg class="p-benefit__tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                            <span>Recognition and verbal "shout-outs" during our weekly poker events.</span>
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

                    <x-btn variant="primary" class="btn--block">{{ __('Send Inquiry') }}</x-btn>
                </form>
            </x-card>
        </div>
    </section>
</x-public-layout>
