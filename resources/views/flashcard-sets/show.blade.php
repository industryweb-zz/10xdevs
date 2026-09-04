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
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    @foreach ($flashcardSet->flashcards as $flashcard)
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold">{{ $flashcard->question }}</p>
                                <p class="text-gray-600">{{ $flashcard->answer }}</p>
                            </div>

                            <x-danger-button
                                x-data=""
                                x-on:click.prevent="$dispatch('open-modal', 'confirm-flashcard-deletion-{{ $flashcard->id }}')"
                            >{{ __('Delete') }}</x-danger-button>

                            <x-modal name="confirm-flashcard-deletion-{{ $flashcard->id }}" focusable>
                                <form method="post" action="{{ route('flashcards.destroy', [$flashcardSet, $flashcard]) }}" class="p-6">
                                    @csrf
                                    @method('delete')

                                    <h2 class="text-lg font-medium text-gray-900">
                                        {{ __('Are you sure you want to delete this flashcard?') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-600">
                                        {{ __('This action cannot be undone.') }}
                                    </p>

                                    <div class="mt-6 flex justify-end">
                                        <x-secondary-button x-on:click="$dispatch('close')">
                                            {{ __('Cancel') }}
                                        </x-secondary-button>

                                        <x-danger-button class="ms-3">
                                            {{ __('Delete') }}
                                        </x-danger-button>
                                    </div>
                                </form>
                            </x-modal>
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
