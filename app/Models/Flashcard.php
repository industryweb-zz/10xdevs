<?php

namespace App\Models;

use Database\Factories\FlashcardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question', 'answer'])]
class Flashcard extends Model
{
    /** @use HasFactory<FlashcardFactory> */
    use HasFactory;

    public function flashcardSet(): BelongsTo
    {
        return $this->belongsTo(FlashcardSet::class);
    }
}
