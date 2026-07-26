<?php

namespace App\Services;

/**
 * Result of a flashcard generation call, before persistence.
 */
final readonly class GeneratedFlashcardSet
{
    /**
     * @param  array<int, array{question: string, answer: string}>  $flashcards
     */
    public function __construct(
        public string $title,
        public array $flashcards,
    ) {}
}
