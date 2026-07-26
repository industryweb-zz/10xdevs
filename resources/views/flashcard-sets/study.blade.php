<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $flashcardSet->title }}
        </h2>
    </x-slot>

    @php
        $cards = $flashcardSet->flashcards->map(fn ($flashcard) => [
            'question' => $flashcard->question,
            'answer' => $flashcard->answer,
        ]);
        $cardLabel = __('Card :current of :total');
    @endphp

    <div class="py-12"
        x-data='{
            cards: @json($cards),
            cardLabel: @json($cardLabel),
            currentIndex: 0,
            flipped: false,
            knownCount: 0,
            unknownCount: 0,
            rate(known) {
                if (known) {
                    this.knownCount++;
                } else {
                    this.unknownCount++;
                }

                if (this.currentIndex === this.cards.length - 1) {
                    this.$refs.knownInput.value = this.knownCount;
                    this.$refs.unknownInput.value = this.unknownCount;
                    this.$refs.resultsForm.submit();
                    return;
                }

                this.currentIndex++;
                this.flipped = false;
            },
        }'>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    <p class="text-sm text-gray-500" x-text="cardLabel.replace(':current', currentIndex + 1).replace(':total', cards.length)"></p>

                    <div class="border rounded-lg p-6 min-h-[8rem] flex items-center justify-center text-center cursor-pointer"
                        @click="flipped = !flipped">
                        <p x-show="!flipped" x-text="cards[currentIndex].question" class="font-semibold text-lg"></p>
                        <p x-show="flipped" x-text="cards[currentIndex].answer" class="text-gray-700"></p>
                    </div>

                    <div class="flex items-center justify-center gap-4" x-show="flipped">
                        <x-secondary-button @click="rate(false)">
                            {{ __('Unknown') }}
                        </x-secondary-button>
                        <x-primary-button @click="rate(true)">
                            {{ __('Known') }}
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>

        <form x-ref="resultsForm" method="POST" action="{{ route('flashcard-sets.results', $flashcardSet) }}" class="hidden">
            @csrf
            <input type="hidden" name="known_count" x-ref="knownInput">
            <input type="hidden" name="unknown_count" x-ref="unknownInput">
        </form>
    </div>
</x-app-layout>
