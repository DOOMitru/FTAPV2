<x-public-layout>
    <div class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 md:flex-row md:items-start">
                <div class="md:w-1/3 flex justify-center md:justify-start">
                    <img class="w-full h-auto max-w-[220px] md:w-auto md:max-w-none md:max-h-[320px] lg:max-h-[360px] object-contain" src="{{ asset('images/hero_logo.png') }}" alt="First to Act Poker League">
                </div>
                <div class="md:w-2/3">
                    <x-section-badge type="mission" class="mb-4" />
                    <h1 class="text-3xl font-bold mb-8 text-gray-900 dark:text-white">Our Mission</h1>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                        <p class="text-lg">
                            The First to Act Poker group provides free to play poker events for the social poker player.
                            We run weekly Texas Hold’m events where poker players have the chance to accumulate points offered during each poker event.
                            The top players at the end of a series will qualify for special events and rewards.
                        </p>
                        <p class="mt-4">
                            Our host venues are introduced to new and repeated customers.
                            Our sponsors have access to a focused group of customers for their product exposure.
                        </p>
                        <p class="mt-4">
                            We will also host special event charity tournaments that will require players to pay an entry fee for the host charity.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Visual Divider -->
            <div class="relative h-px bg-gradient-to-r from-transparent via-gray-300 dark:via-gray-700 to-transparent my-16">
                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 px-4 bg-gray-100 dark:bg-gray-800">
                    <svg class="w-8 h-8 text-amber-500/30" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2z" /></svg>
                </div>
            </div>

            <!-- Sponsorship Section -->
            <div id="become-a-sponsor" class="pt-8">
                <x-section-badge type="sponsor" class="mb-4" />
                <h2 class="text-3xl font-bold mb-8 text-gray-900 dark:text-white">Become a Sponsor</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
                    <div class="prose dark:prose-invert max-w-none">
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            <strong>First to Act Poker</strong> is a growing Regina-based start-up dedicated to hosting vibrant, community-focused free-to-play poker leagues. We are currently bringing the excitement of the game to multiple venues across the city, providing a fun and social environment for local players.
                        </p>
                        
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            We are inviting local businesses to partner with us as league sponsors. For a modest annual contribution of just <span class="font-bold text-amber-600 dark:text-amber-400">$100</span>, your brand will gain consistent exposure to our dedicated and engaged community. This is a highly cost-effective way to advertise your business both on our official website and on our event posters displayed at all our partner venues.
                        </p>

                        <div class="bg-white dark:bg-slate-900/50 border border-gray-200 dark:border-amber-500/30 rounded-xl p-6 mb-8 shadow-sm dark:shadow-[0_0_15px_rgba(245,158,11,0.05)]">
                            <h3 class="flex items-center text-amber-600 dark:text-amber-400 font-bold mb-4 uppercase tracking-tight">
                                <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.82v-1.91c-.39-.05-.77-.15-1.14-.3-.49-.21-.92-.49-1.28-.84l1.34-1.34c.15.15.34.28.56.39.22.11.44.18.66.21.22.03.45.04.68.03.22-.01.44-.04.66-.08.23-.05.45-.12.67-.22.22-.11.41-.24.57-.4.16-.16.29-.35.39-.57.1-.22.15-.45.15-.7 0-.27-.06-.54-.18-.8s-.29-.5-.51-.71-.46-.39-.73-.55c-.27-.15-.55-.28-.84-.37-.3-.09-.59-.17-.89-.24-.29-.07-.58-.15-.86-.24-.29-.09-.56-.22-.81-.37-.25-.15-.47-.32-.67-.53-.19-.21-.35-.45-.46-.72-.11-.27-.17-.57-.17-.9 0-.44.1-.85.29-1.23s.47-.71.84-.98c.37-.27.81-.48 1.33-.61V4h2.82v1.94c.34.04.66.12.98.24.32.12.61.28.87.48.26.2.49.44.68.72l-1.34 1.34c-.11-.15-.26-.29-.46-.42-.2-.13-.42-.23-.67-.3-.25-.07-.52-.1-.83-.1-.31 0-.6.04-.88.13s-.53.21-.75.38c-.22.17-.39.38-.5.64s-.17.54-.17.84c0 .32.06.63.17.91s.28.53.5.76c.22.23.47.43.76.59.29.16.6.28.92.38.33.09.66.18.99.27.33.09.65.2.95.33.31.13.58.29.83.48s.45.41.6.67c.15.26.25.55.3.88.05.33.08.67.08 1.05 0 .54-.11 1.03-.32 1.48-.21.45-.51.84-.9 1.15-.39.31-.85.55-1.4.72z" /></svg>
                                Where does the money go?
                            </h3>
                            <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">
                                100% of sponsorship fees are pooled to fund a substantial grand prize for our annual Season Finale Tournament. By becoming a sponsor, you’re not just advertising—you’re directly supporting the Regina poker community and helping us reward the skill and sportsmanship of our top players.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xl font-bold mb-4">Sponsorship Benefits</h3>
                            <ul class="list-none space-y-4">
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500/20 text-green-500 flex items-center justify-center mr-3 mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <span class="text-gray-600 dark:text-gray-400">High-visibility logo placement on our league posters in partner venues.</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500/20 text-green-500 flex items-center justify-center mr-3 mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <span class="text-gray-600 dark:text-gray-400">Dedicated brand logo and backlink on our official website.</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500/20 text-green-500 flex items-center justify-center mr-3 mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <span class="text-gray-600 dark:text-gray-400">Recognition and verbal "shout-outs" during our weekly poker events.</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-700 p-8 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-600">
                        <h3 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">Sponsorship Inquiry</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-8 text-sm">
                            Ready to grow your business while supporting local poker? Send us an inquiry below and we'll get in touch to finalize your sponsorship.
                        </p>
                        <form action="#" method="POST" class="space-y-5">
                            @csrf
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Business or Representative Name</label>
                                <input type="text" name="name" id="name" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors" placeholder="e.g. Ace High Beverages" required>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                <input type="email" name="email" id="email" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors" placeholder="contact@example.com" required>
                            </div>
                            <div>
                                <label for="message" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Message</label>
                                <textarea name="message" id="message" rows="4" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors" placeholder="How can we help highlight your brand?" required></textarea>
                            </div>
                            <button type="submit" class="w-full py-4 px-6 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest transition-all duration-300 shadow-lg hover:shadow-indigo-500/30 transform hover:-translate-y-1">
                                Send Inquiry
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
