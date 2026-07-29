<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsOwnershipEnforced;
use Tests\TestCase;

class FlashcardSetOwnershipTest extends TestCase
{
    use AssertsOwnershipEnforced;
    use RefreshDatabase;

    public static function boundRoutesProvider(): array
    {
        return [
            'show' => ['get', 'flashcard-sets.show'],
            'study' => ['get', 'flashcard-sets.study'],
        ];
    }

    #[DataProvider('boundRoutesProvider')]
    public function test_other_user_is_forbidden_from_bound_route(string $method, string $routeName): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();

        $this->assertRouteForbiddenToOtherUser($otherUser, $flashcardSet, $method, $routeName);
    }

    public function test_other_user_is_forbidden_from_results_route(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $flashcardSet = FlashcardSet::factory()->for($owner)->create();
        Flashcard::factory()->for($flashcardSet)->create();

        $this->assertRouteForbiddenToOtherUser($otherUser, $flashcardSet, 'post', 'flashcard-sets.results', [
            'known_count' => 1,
            'unknown_count' => 0,
        ]);
    }
}
