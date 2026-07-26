<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $flashcardSet->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center space-y-2">
                    <p class="text-2xl font-semibold">
                        {{ __(':known of :total known', ['known' => $knownCount, 'total' => $knownCount + $unknownCount]) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
