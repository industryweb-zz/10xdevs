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

                            <div class="flex gap-2 shrink-0">
                                <x-secondary-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'edit-flashcard-{{ $flashcard->id }}')"
                                >{{ __('Edit') }}</x-secondary-button>

                                <x-danger-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-flashcard-deletion-{{ $flashcard->id }}')"
                                >{{ __('Delete') }}</x-danger-button>
                            </div>

                            <x-modal name="edit-flashcard-{{ $flashcard->id }}" :show="$errors->any() && (int) old('flashcard_id') === $flashcard->id" focusable>
                                <form method="post" action="{{ route('flashcards.update', [$flashcardSet, $flashcard]) }}" class="p-6">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="flashcard_id" value="{{ $flashcard->id }}">

                                    <h2 class="text-lg font-medium text-gray-900">
                                        {{ __('Edit flashcard') }}
                                    </h2>

                                    @php
                                        $isThisCardsError = (int) old('flashcard_id') === $flashcard->id;
                                    @endphp

                                    <div class="mt-6">
                                        <x-input-label for="question-{{ $flashcard->id }}" :value="__('Question')" />
                                        <textarea id="question-{{ $flashcard->id }}" name="question" rows="3" required
                                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $isThisCardsError ? old('question') : $flashcard->question }}</textarea>
                                        <x-input-error :messages="$isThisCardsError ? $errors->get('question') : []" class="mt-2" />
                                    </div>

                                    <div class="mt-4">
                                        <x-input-label for="answer-{{ $flashcard->id }}" :value="__('Answer')" />
                                        <textarea id="answer-{{ $flashcard->id }}" name="answer" rows="3" required
                                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $isThisCardsError ? old('answer') : $flashcard->answer }}</textarea>
                                        <x-input-error :messages="$isThisCardsError ? $errors->get('answer') : []" class="mt-2" />
                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <x-secondary-button x-on:click="$dispatch('close')">
                                            {{ __('Cancel') }}
                                        </x-secondary-button>

                                        <x-primary-button class="ms-3">
                                            {{ __('Save') }}
                                        </x-primary-button>
                                    </div>
                                </form>
                            </x-modal>

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
