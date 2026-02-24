<x-public-layout>
    <div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-section-badge type="sponsor" class="mb-4" />
            <h1 class="text-3xl font-bold mb-8 text-gray-900 dark:text-white">Become a Sponsor</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="prose dark:prose-invert max-w-none">
                    <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                        <strong>First to Act Poker</strong> is a growing Regina-based start-up dedicated to hosting vibrant, community-focused free-to-play poker leagues. We are currently bringing the excitement of the game to multiple venues across the city, providing a fun and social environment for local players.
                    </p>
                    
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        We are inviting local businesses to partner with us as league sponsors. For a modest annual contribution of just <span class="font-bold text-amber-600 dark:text-amber-400">$100</span>, your brand will gain consistent exposure to our dedicated and engaged community. This is a highly cost-effective way to advertise your business both on our official website and on our event posters displayed at all our partner venues.
                    </p>

                    <div class="bg-gray-50 dark:bg-slate-900/50 border border-gray-200 dark:border-amber-500/30 rounded-lg p-5 mb-8 shadow-sm dark:shadow-[0_0_15px_rgba(245,158,11,0.05)]">
                        <h3 class="flex items-center text-amber-600 dark:text-amber-400 font-bold mb-3 uppercase tracking-tight">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.82v-1.91c-.39-.05-.77-.15-1.14-.3-.49-.21-.92-.49-1.28-.84l1.34-1.34c.15.15.34.28.56.39.22.11.44.18.66.21.22.03.45.04.68.03.22-.01.44-.04.66-.08.23-.05.45-.12.67-.22.22-.11.41-.24.57-.4.16-.16.29-.35.39-.57.1-.22.15-.45.15-.7 0-.27-.06-.54-.18-.8s-.29-.5-.51-.71-.46-.39-.73-.55c-.27-.15-.55-.28-.84-.37-.3-.09-.59-.17-.89-.24-.29-.07-.58-.15-.86-.24-.29-.09-.56-.22-.81-.37-.25-.15-.47-.32-.67-.53-.19-.21-.35-.45-.46-.72-.11-.27-.17-.57-.17-.9 0-.44.1-.85.29-1.23s.47-.71.84-.98c.37-.27.81-.48 1.33-.61V4h2.82v1.94c.34.04.66.12.98.24.32.12.61.28.87.48.26.2.49.44.68.72l-1.34 1.34c-.11-.15-.26-.29-.46-.42-.2-.13-.42-.23-.67-.3-.25-.07-.52-.1-.83-.1-.31 0-.6.04-.88.13s-.53.21-.75.38c-.22.17-.39.38-.5.64s-.17.54-.17.84c0 .32.06.63.17.91s.28.53.5.76c.22.23.47.43.76.59.29.16.6.28.92.38.33.09.66.18.99.27.33.09.65.2.95.33.31.13.58.29.83.48s.45.41.6.67c.15.26.25.55.3.88.05.33.08.67.08 1.05 0 .54-.11 1.03-.32 1.48-.21.45-.51.84-.9 1.15-.39.31-.85.55-1.4.72z" /></svg>
                            Where does the money go?
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">
                            100% of sponsorship fees are pooled to fund a substantial grand prize for our annual Season Finale Tournament. By becoming a sponsor, you’re not just advertising—you’re directly supporting the Regina poker community and helping us reward the skill and sportsmanship of our top players.
                        </p>
                    </div>

                    <h3 class="text-xl font-bold mb-4">Sponsorship Benefits</h3>
                    <ul class="list-none space-y-3 text-gray-600 dark:text-gray-400">
                        <li class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Logo & Link on our official website
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Brand placement on all league posters
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Recognition at our tournament events
                        </li>
                    </ul>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Sponsorship Inquiry</h2>
                    <form action="#" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name / Business Name</label>
                            <input type="text" name="name" id="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                            <input type="email" name="email" id="email" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="mb-4">
                            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                            <textarea name="message" id="message" rows="4" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required></textarea>
                        </div>
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Send Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
