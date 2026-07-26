<?php

namespace Tests\Feature;

use App\Exceptions\FlashcardGenerationException;
use App\Models\FlashcardSet;
use App\Models\User;
use App\Services\FlashcardGenerator;
use App\Services\GeneratedFlashcardSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_paste_generates_a_flashcard_set_and_redirects_to_preview(): void
    {
        $user = User::factory()->create();

        $this->app->bind(FlashcardGenerator::class, fn () => new class implements FlashcardGenerator
        {
            public function generate(string $sourceText): GeneratedFlashcardSet
            {
                return new GeneratedFlashcardSet(
                    title: 'Photosynthesis Basics',
                    flashcards: [
                        ['question' => 'What is photosynthesis?', 'answer' => 'The process plants use to convert light into energy.'],
                    ],
                );
            }
        });

        $response = $this->actingAs($user)->post('/flashcard-sets', [
            'text' => 'Photosynthesis is the process by which plants convert light into energy.',
        ]);

        $flashcardSet = FlashcardSet::sole();

        $response->assertRedirect(route('flashcard-sets.show', $flashcardSet));
        $this->assertSame($user->id, $flashcardSet->user_id);
        $this->assertSame('Photosynthesis Basics', $flashcardSet->title);
        $this->assertCount(1, $flashcardSet->flashcards);
    }

    public function test_generator_failure_redirects_back_with_a_flash_error_and_creates_no_set(): void
    {
        $user = User::factory()->create();

        $this->app->bind(FlashcardGenerator::class, fn () => new class implements FlashcardGenerator
        {
            public function generate(string $sourceText): GeneratedFlashcardSet
            {
                throw new FlashcardGenerationException('boom');
            }
        });

        $response = $this->actingAs($user)->post('/flashcard-sets', [
            'text' => 'Some source text.',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('flashcard_sets', 0);
    }

    public function test_text_exceeding_max_length_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/flashcard-sets', [
            'text' => str_repeat('a', 8001),
        ]);

        $response->assertSessionHasErrors('text');
        $this->assertNull(session()->getOldInput('text'));
        $this->assertDatabaseCount('flashcard_sets', 0);
    }

    public function test_viewing_another_users_flashcard_set_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->get(route('flashcard-sets.show', $flashcardSet));

        $response->assertForbidden();
    }
}
