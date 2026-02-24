<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('User Details') }}: {{ $user->first_name }} {{ $user->last_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="flex items-center space-x-4">
                                <img class="h-20 w-20 rounded-full object-cover shadow-lg border-2 border-indigo-500" src="{{ $user->profile_image_url }}" alt="{{ $user->display_name }}">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Personal Information</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage user details and role.</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-1">
                                    <p><span class="font-bold">First Name:</span> {{ $user->first_name }}</p>
                                    <p><span class="font-bold">Last Name:</span> {{ $user->last_name }}</p>
                                    <p><span class="font-bold">Nickname:</span> {{ $user->nickname ?? 'N/A' }}</p>
                                    <p><span class="font-bold">Email:</span> {{ $user->email }}</p>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Account Details</h3>
                                <div class="mt-2 space-y-1">
                                    <p><span class="font-bold">Role:</span> {{ $user->is_admin ? 'Administrator' : 'Player' }}</p>
                                    <p><span class="font-bold">Joined:</span> {{ $user->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start justify-end space-x-3">
                            <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:border-yellow-900 focus:ring ring-yellow-300 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Edit User') }}
                            </a>
                            <a href="{{ route('users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Back to List') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
