<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Points Structure Entry') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('poker.points-structure.update', $points_structure) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="place" :value="__('Place')" />
                            <x-text-input id="place" name="place" type="number" class="mt-1 block w-full" :value="old('place', $points_structure->place)" required autofocus min="1" />
                            <x-input-error class="mt-2" :messages="$errors->get('place')" />
                        </div>

                        <div>
                            <x-input-label for="points" :value="__('Points')" />
                            <x-text-input id="points" name="points" type="number" class="mt-1 block w-full" :value="old('points', $points_structure->points)" required min="0" />
                            <x-input-error class="mt-2" :messages="$errors->get('points')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update') }}</x-primary-button>
                            <a href="{{ route('poker.points-structure.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
