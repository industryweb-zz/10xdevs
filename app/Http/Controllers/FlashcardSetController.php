<?php

namespace App\Http\Controllers;

use App\Exceptions\FlashcardGenerationException;
use App\Http\Requests\GenerateFlashcardsRequest;
use App\Models\FlashcardSet;
use App\Services\FlashcardGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FlashcardSetController extends Controller
{
    public function __construct(private readonly FlashcardGenerator $generator) {}

    public function index(Request $request): View
    {
        return view('flashcard-sets.index', [
            'flashcardSets' => $request->user()->flashcardSets()->latest()->get(),
        ]);
    }

    public function store(GenerateFlashcardsRequest $request): RedirectResponse
    {
        try {
            $generated = $this->generator->generate($request->validated('text'));
        } catch (FlashcardGenerationException) {
            return redirect()->route('dashboard')
                ->with('error', __('Something went wrong while generating your flashcards. Please try again.'));
        }

        $flashcardSet = DB::transaction(function () use ($request, $generated) {
            $flashcardSet = $request->user()->flashcardSets()->create([
                'title' => $generated->title,
            ]);

            $flashcardSet->flashcards()->createMany($generated->flashcards);

            return $flashcardSet;
        });

        return redirect()->route('flashcard-sets.show', $flashcardSet);
    }

    public function show(FlashcardSet $flashcardSet): View
    {
        abort_unless($flashcardSet->user_id === auth()->id(), 403);

        return view('flashcard-sets.show', [
            'flashcardSet' => $flashcardSet->load('flashcards'),
        ]);
    }
}
