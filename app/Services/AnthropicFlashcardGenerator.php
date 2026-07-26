<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Messages\TextBlock;
use App\Exceptions\FlashcardGenerationException;
use JsonException;

class AnthropicFlashcardGenerator implements FlashcardGenerator
{
    private const MODEL = 'claude-haiku-4-5';

    private const MAX_TOKENS = 4096;

    public function __construct(private readonly Client $client) {}

    public function generate(string $sourceText): GeneratedFlashcardSet
    {
        try {
            $response = $this->client->messages->create(
                maxTokens: self::MAX_TOKENS,
                model: self::MODEL,
                messages: [
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($sourceText),
                    ],
                ],
                outputConfig: [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $this->outputSchema(),
                    ],
                ],
            );
        } catch (APIException $exception) {
            throw new FlashcardGenerationException(
                'Flashcard generation failed while calling the Anthropic API.',
                previous: $exception,
            );
        }

        $textBlock = collect($response->content)
            ->first(fn (mixed $block): bool => $block instanceof TextBlock);

        if (! $textBlock instanceof TextBlock) {
            throw new FlashcardGenerationException('Anthropic response contained no text block.');
        }

        try {
            /** @var array{title?: mixed, flashcards?: mixed} $decoded */
            $decoded = json_decode($textBlock->text, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FlashcardGenerationException(
                'Anthropic response was not valid JSON.',
                previous: $exception,
            );
        }

        return $this->toGeneratedFlashcardSet($decoded);
    }

    private function buildPrompt(string $sourceText): string
    {
        return <<<PROMPT
            You turn educational source text into flashcards for self-study.

            Identify the key concepts and definitions in the text below and turn each
            one into an open question with a complete answer derived from the text.
            Generate between 5 and 15 flashcards depending on how much the text covers.
            Also produce a short (3-8 word) title summarizing the source text's topic.
            Write the title, questions, and answers in the same language as the source
            text below.

            Source text:
            """
            {$sourceText}
            """
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'flashcards' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'answer' => ['type' => 'string'],
                        ],
                        'required' => ['question', 'answer'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['title', 'flashcards'],
            'additionalProperties' => false,
        ];
    }

    private function toGeneratedFlashcardSet(array $decoded): GeneratedFlashcardSet
    {
        $title = $decoded['title'] ?? null;
        $flashcards = $decoded['flashcards'] ?? null;

        if (! is_string($title) || trim($title) === '' || ! is_array($flashcards) || $flashcards === []) {
            throw new FlashcardGenerationException('Anthropic response did not contain a valid flashcard set.');
        }

        $normalized = [];

        foreach ($flashcards as $flashcard) {
            if (
                ! is_array($flashcard)
                || ! isset($flashcard['question'], $flashcard['answer'])
                || ! is_string($flashcard['question'])
                || ! is_string($flashcard['answer'])
                || trim($flashcard['question']) === ''
                || trim($flashcard['answer']) === ''
            ) {
                throw new FlashcardGenerationException('Anthropic response contained a malformed flashcard.');
            }

            $normalized[] = [
                'question' => $flashcard['question'],
                'answer' => $flashcard['answer'],
            ];
        }

        return new GeneratedFlashcardSet(title: $title, flashcards: $normalized);
    }
}
