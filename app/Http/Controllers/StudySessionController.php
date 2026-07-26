<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitStudyResultsRequest;
use App\Models\FlashcardSet;
use Illuminate\View\View;

class StudySessionController extends Controller
{
    public function study(FlashcardSet $flashcardSet): View
    {
        abort_unless($flashcardSet->user_id === auth()->id(), 403);

        return view('flashcard-sets.study', [
            'flashcardSet' => $flashcardSet->load('flashcards'),
        ]);
    }

    public function results(SubmitStudyResultsRequest $request, FlashcardSet $flashcardSet): View
    {
        abort_unless($flashcardSet->user_id === auth()->id(), 403);

        $knownCount = $request->validated('known_count');
        $unknownCount = $request->validated('unknown_count');

        abort_unless(
            $knownCount + $unknownCount === $flashcardSet->flashcards()->count(),
            422
        );

        return view('flashcard-sets.results', [
            'flashcardSet' => $flashcardSet,
            'knownCount' => $knownCount,
            'unknownCount' => $unknownCount,
        ]);
    }
}
