<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_their_flashcard(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create([
            'question' => 'Old question',
            'answer' => 'Old answer',
        ]);

        $response = $this->actingAs($user)->patch(route('flashcards.update', [$flashcardSet, $flashcard]), [
            'question' => 'New question',
            'answer' => 'New answer',
        ]);

        $response->assertRedirect(route('flashcard-sets.show', $flashcardSet));
        $this->assertSame('New question', $flashcard->fresh()->question);
        $this->assertSame('New answer', $flashcard->fresh()->answer);
    }

    public function test_question_and_answer_are_required(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create([
            'question' => 'Old question',
            'answer' => 'Old answer',
        ]);

        $response = $this->actingAs($user)->patch(route('flashcards.update', [$flashcardSet, $flashcard]), [
            'question' => '',
            'answer' => '',
        ]);

        $response->assertSessionHasErrors(['question', 'answer']);
        $this->assertSame('Old question', $flashcard->fresh()->question);
    }

    public function test_other_user_is_forbidden_from_updating_flashcard(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create(['question' => 'Old question']);

        $response = $this->actingAs($otherUser)->patch(route('flashcards.update', [$flashcardSet, $flashcard]), [
            'question' => 'Hacked',
            'answer' => 'Hacked',
        ]);

        $response->assertForbidden();
        $this->assertSame('Old question', $flashcard->fresh()->question);
    }

    public function test_flashcard_not_belonging_to_flashcard_set_returns_not_found(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        $otherSet = FlashcardSet::factory()->for($user)->create();
        $flashcard = Flashcard::factory()->for($otherSet)->create(['question' => 'Old question']);

        $response = $this->actingAs($user)->patch(route('flashcards.update', [$flashcardSet, $flashcard]), [
            'question' => 'New question',
            'answer' => 'New answer',
        ]);

        $response->assertNotFound();
        $this->assertSame('Old question', $flashcard->fresh()->question);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $flashcardSet = FlashcardSet::factory()->create();
        $flashcard = Flashcard::factory()->for($flashcardSet)->create();

        $response = $this->patch(route('flashcards.update', [$flashcardSet, $flashcard]), [
            'question' => 'New question',
            'answer' => 'New answer',
        ]);

        $response->assertRedirect(route('login'));
    }
}
