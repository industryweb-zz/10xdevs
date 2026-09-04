<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\FlashcardSet;
use Illuminate\Http\RedirectResponse;

class FlashcardController extends Controller
{
    public function destroy(FlashcardSet $flashcardSet, Flashcard $flashcard): RedirectResponse
    {
        abort_unless($flashcardSet->user_id === auth()->id(), 403);
        abort_unless($flashcard->flashcard_set_id === $flashcardSet->id, 404);

        $flashcard->delete();

        return redirect()->route('flashcard-sets.show', $flashcardSet)
            ->with('status', __('Flashcard deleted.'));
    }
}
