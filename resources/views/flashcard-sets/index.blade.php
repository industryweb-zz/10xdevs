<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My sets') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($flashcardSets->isEmpty())
                        <p class="text-gray-600">
                            {{ __("You don't have any flashcard sets yet.") }}
                        </p>

                        <a href="{{ route('dashboard') }}"
                            class="inline-flex items-center mt-4 px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Generate your first set') }}
                        </a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($flashcardSets as $flashcardSet)
                                <li class="py-4 flex items-center justify-between">
                                    <a href="{{ route('flashcard-sets.show', $flashcardSet) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                        {{ $flashcardSet->title }}
                                    </a>
                                    <span class="text-sm text-gray-500">
                                        {{ $flashcardSet->created_at->format('Y-m-d H:i') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
