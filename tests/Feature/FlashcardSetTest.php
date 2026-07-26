<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardSetTest extends TestCase
{
    use RefreshDatabase;

    public function test_flashcard_set_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();

        $this->assertTrue($flashcardSet->user->is($user));
    }

    public function test_flashcard_set_has_many_flashcards(): void
    {
        $flashcardSet = FlashcardSet::factory()->create();
        Flashcard::factory()->count(3)->for($flashcardSet)->create();

        $this->assertCount(3, $flashcardSet->fresh()->flashcards);
    }

    public function test_flashcard_belongs_to_a_flashcard_set(): void
    {
        $flashcardSet = FlashcardSet::factory()->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create();

        $this->assertTrue($flashcard->flashcardSet->is($flashcardSet));
    }

    public function test_deleting_a_flashcard_set_deletes_its_flashcards(): void
    {
        $flashcardSet = FlashcardSet::factory()->create();
        Flashcard::factory()->count(2)->for($flashcardSet)->create();

        $flashcardSet->delete();

        $this->assertDatabaseCount('flashcards', 0);
    }

    public function test_deleting_a_user_deletes_their_flashcard_sets(): void
    {
        $user = User::factory()->create();
        FlashcardSet::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseCount('flashcard_sets', 0);
    }
}
