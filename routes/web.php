<?php

use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\FlashcardSetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudySessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/flashcard-sets', [FlashcardSetController::class, 'store'])->name('flashcard-sets.store');
    Route::get('/flashcard-sets', [FlashcardSetController::class, 'index'])->name('flashcard-sets.index');
    Route::get('/flashcard-sets/{flashcardSet}', [FlashcardSetController::class, 'show'])->name('flashcard-sets.show');

    Route::delete('/flashcard-sets/{flashcardSet}/flashcards/{flashcard}', [FlashcardController::class, 'destroy'])->name('flashcards.destroy');

    Route::get('/flashcard-sets/{flashcardSet}/study', [StudySessionController::class, 'study'])->name('flashcard-sets.study');
    Route::post('/flashcard-sets/{flashcardSet}/results', [StudySessionController::class, 'results'])->name('flashcard-sets.results');
});

require __DIR__.'/auth.php';
