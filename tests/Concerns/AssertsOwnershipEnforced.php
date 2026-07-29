<?php

namespace Tests\Concerns;

use App\Models\FlashcardSet;
use App\Models\User;

trait AssertsOwnershipEnforced
{
    protected function assertRouteForbiddenToOtherUser(
        User $otherUser,
        FlashcardSet $flashcardSet,
        string $method,
        string $routeName,
        array $data = [],
    ): void {
        $response = $this->actingAs($otherUser)->{$method}(route($routeName, $flashcardSet), $data);

        $response->assertForbidden();
    }
}
