<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudySessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_counts_render_the_correct_score(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        Flashcard::factory()->count(3)->for($flashcardSet)->create();

        $response = $this->actingAs($user)->post(route('flashcard-sets.results', $flashcardSet), [
            'known_count' => 2,
            'unknown_count' => 1,
        ]);

        $response->assertOk();
        $response->assertSee('2');
    }

    public function test_mismatched_counts_return_422(): void
    {
        $user = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($user)->create();
        Flashcard::factory()->count(3)->for($flashcardSet)->create();

        $response = $this->actingAs($user)->post(route('flashcard-sets.results', $flashcardSet), [
            'known_count' => 1,
            'unknown_count' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_cross_user_access_to_study_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->get(route('flashcard-sets.study', $flashcardSet));

        $response->assertForbidden();
    }

    public function test_cross_user_access_to_results_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();
        Flashcard::factory()->for($flashcardSet)->create();

        $response = $this->actingAs($otherUser)->post(route('flashcard-sets.results', $flashcardSet), [
            'known_count' => 1,
            'unknown_count' => 0,
        ]);

        $response->assertForbidden();
    }
}
