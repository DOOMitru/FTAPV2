<x-public-layout>
    <div class="bg-gray-50 dark:bg-gray-900 transition-colors duration-500 overflow-hidden relative min-h-screen pt-20">
        <!-- Background Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none text-transparent">.
            <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] bg-indigo-600/5 dark:bg-indigo-500/10 blur-[120px] rounded-full"></div>
            <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] bg-amber-500/5 dark:bg-amber-400/10 blur-[120px] rounded-full"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-24 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-start">
                
                <!-- Left Column: Impactful Content -->
                <div class="space-y-10">
                    <div>
                        <x-section-badge type="mission" class="mb-6" label="Connect with us" />
                        <h1 class="text-5xl sm:text-7xl font-black text-gray-900 dark:text-white leading-[1.1] tracking-tighter mb-8">
                            Join the <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-500 to-amber-700 dark:from-amber-400 dark:to-yellow-600">First to Act</span> Circle
                        </h1>
                        <p class="text-xl text-gray-600 dark:text-gray-400 leading-relaxed max-w-xl">
                            Whether you're looking to join the league, discuss partnership opportunities, or need technical assistance, our stewards are ready to connect.
                        </p>
                    </div>

                    <!-- Contact Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div class="group">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-500 flex items-center justify-center border border-amber-100 dark:border-amber-700/30 group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Email Us</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">steward@firsttoact.com</p>
                        </div>

                        <div class="group">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-500 flex items-center justify-center border border-amber-100 dark:border-amber-700/30 group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">League Discord</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">discord.gg/firsttoact</p>
                        </div>
                    </div>

                    <!-- Decorative Quote/Badge -->
                    <div class="pt-8 border-t border-gray-200 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-12 bg-amber-600 rounded-full shadow-[0_0_15px_rgba(217,119,6,0.3)]"></div>
                            <p class="text-xs font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.4em]">
                                "Decisions at the table, <br/> Integrity everywhere else."
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Form Card (Matched to About Page) -->
                <div class="relative">
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row items-center gap-6 mb-10">
                            <img src="{{ asset('images/hero_logo.png') }}" alt="First to Act Poker League" class="w-20 h-auto transition-transform duration-500 hover:scale-110">
                            <div class="text-center sm:text-left">
                                <h2 class="text-2xl font-bold mb-1 text-gray-900 dark:text-white uppercase tracking-tight">Send a <span class="text-amber-600">Transmission</span></h2>
                                <p class="text-gray-500 dark:text-gray-400 text-xs font-medium uppercase tracking-widest">Connect with the league</p>
                            </div>
                        </div>

                        <form action="#" method="POST" class="space-y-5">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label for="name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                    <input type="text" name="name" id="name" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors" placeholder="Your name" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                    <input type="email" name="email" id="email" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors" placeholder="contact@example.com" required>
                                </div>
                            </div>

                            <div>
                                <label for="subject" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                                <select name="subject" id="subject" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors">
                                    <option>General Inquiry</option>
                                    <option>League Registration</option>
                                    <option>Commercial Partnership</option>
                                    <option>Technical Support</option>
                                </select>
                            </div>

                            <div>
                                <label for="message" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Message</label>
                                <textarea name="message" id="message" rows="4" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors" placeholder="How can we help?" required></textarea>
                            </div>

                            <button type="submit" class="w-full py-4 px-6 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase tracking-widest transition-all duration-300 shadow-lg hover:shadow-indigo-500/30 transform hover:-translate-y-1">
                                Initialize Connection
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-public-layout>
