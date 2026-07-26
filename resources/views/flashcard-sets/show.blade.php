<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $flashcardSet->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    @foreach ($flashcardSet->flashcards as $flashcard)
                        <div>
                            <p class="font-semibold">{{ $flashcard->question }}</p>
                            <p class="text-gray-600">{{ $flashcard->answer }}</p>
                        </div>
                    @endforeach

                    <a href="{{ route('flashcard-sets.study', $flashcardSet) }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Start session') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
