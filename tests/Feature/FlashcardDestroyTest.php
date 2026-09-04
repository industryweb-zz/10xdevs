<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_their_flashcard(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create();

        $response = $this->actingAs($user)->delete(route('flashcards.destroy', [$flashcardSet, $flashcard]));

        $response->assertRedirect(route('flashcard-sets.show', $flashcardSet));
        $this->assertModelMissing($flashcard);
    }

    public function test_other_user_is_forbidden_from_deleting_flashcard(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create();

        $response = $this->actingAs($otherUser)->delete(route('flashcards.destroy', [$flashcardSet, $flashcard]));

        $response->assertForbidden();
        $this->assertModelExists($flashcard);
    }

    public function test_flashcard_not_belonging_to_flashcard_set_returns_not_found(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        $otherSet = FlashcardSet::factory()->for($user)->create();
        $flashcard = Flashcard::factory()->for($otherSet)->create();

        $response = $this->actingAs($user)->delete(route('flashcards.destroy', [$flashcardSet, $flashcard]));

        $response->assertNotFound();
        $this->assertModelExists($flashcard);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $flashcardSet = FlashcardSet::factory()->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create();

        $response = $this->delete(route('flashcards.destroy', [$flashcardSet, $flashcard]));

        $response->assertRedirect(route('login'));
    }
}
