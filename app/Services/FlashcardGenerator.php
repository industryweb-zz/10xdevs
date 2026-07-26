<?php

namespace App\Services;

use App\Exceptions\FlashcardGenerationException;

interface FlashcardGenerator
{
    /**
     * Generate a flashcard set from pasted source text.
     *
     * @throws FlashcardGenerationException if the call fails, times out, or the
     *                                      response can't be parsed into valid flashcards.
     */
    public function generate(string $sourceText): GeneratedFlashcardSet;
}
