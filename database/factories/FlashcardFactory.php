<?php

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flashcard>
 */
class FlashcardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flashcard_set_id' => FlashcardSet::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->sentence(),
        ];
    }
}
