<?php

namespace Tests\Feature;

use App\Models\FlashcardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardSetIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_own_sets_newest_first(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $older = FlashcardSet::factory()->for($user)->create(['title' => 'Older set', 'created_at' => now()->subDay()]);
        $newer = FlashcardSet::factory()->for($user)->create(['title' => 'Newer set', 'created_at' => now()]);
        FlashcardSet::factory()->for($otherUser)->create(['title' => 'Someone else\'s set']);

        $response = $this->actingAs($user)->get(route('flashcard-sets.index'));

        $response->assertOk();
        $response->assertSeeInOrder([$newer->title, $older->title]);
        $response->assertDontSee('Someone else\'s set');
    }

    public function test_user_with_no_sets_sees_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('flashcard-sets.index'));

        $response->assertOk();
        $response->assertSee(__("You don't have any flashcard sets yet."));
        $response->assertSee(route('dashboard'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('flashcard-sets.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_response_never_includes_another_users_flashcard_set_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        FlashcardSet::factory()->for($user)->create();
        $otherUsersSet = FlashcardSet::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('flashcard-sets.index'));

        $response->assertOk();
        $response->assertViewHas('flashcardSets', fn ($sets) => $sets->doesntContain('id', $otherUsersSet->id));
    }
}
